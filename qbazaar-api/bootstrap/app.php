<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Exceptions\NotFoundErrorCode;
use App\Http\Middleware\ApiResponseWrapper;
use App\Http\Middleware\EnsurePhoneVerified;
use App\Http\Middleware\EnsureStaff;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\Idempotent;
use App\Http\Middleware\LocaleMiddleware;
use App\Http\Middleware\TrackClient;
use App\Http\Middleware\VerifyTurnstile;
use App\Jobs\Ads\ExpireOldAdsJob;
use App\Jobs\Ads\FlushAdViewCountsJob;
use App\Jobs\Catalog\WarmCatalogCacheJob;
use App\Jobs\Ledger\ReconcileLedgerJob;
use App\Jobs\Offers\ExpireOldOffersJob;
use App\Jobs\Orders\ReleaseDueEscrowOrdersJob;
use App\Jobs\Promotions\ExpirePromotionsJob;
use App\Jobs\Search\SyncAdViewCountsJob;
use App\Jobs\SweepDueAccountDeletionsJob;
use App\Models\DatabaseNotification;
use App\Models\DataExport;
use App\Models\OtpCode;
use App\Models\RecentView;
use App\Models\RefreshToken;
use App\Models\TrustedDevice;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Helper used by the exception renderers — keeps the envelope shape in one place.
 *
 * MUST be declared before the `return Application::configure(...)` chain below,
 * otherwise it never reaches PHP's symbol table (everything after `return` is
 * dead code) and exception rendering blows up with "undefined function jsonError".
 */
if (! function_exists('jsonError')) {
    /**
     * @param array<string, mixed>|null $details
     */
    function jsonError(ErrorCode $code, string $message, ?array $details = null, ?string $requestId = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code->value,
                'message_key' => $code->messageKey(),
                'message' => $message,
                'details' => $details,
                'request_id' => $requestId,
            ],
        ], $code->httpStatus());
    }
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api_v1.php',
        apiPrefix: 'api/v1',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        // Note: RateLimiter definitions live in AppServiceProvider::boot() because the
        // `then:` closure here only runs when routes are NOT cached. After route:cache,
        // throttle middleware would crash with "Rate limiter [api] is not defined".
    )
    // Discovery is the only listener registration mechanism: a manual
    // Event::listen for a discovered listener makes it run twice.
    ->withEvents(discover: [__DIR__ . '/../app/Listeners'])
    // Every client (web SPA + mobile) authenticates with a Sanctum Bearer
    // token, so channel auth lives beside the API instead of behind the
    // session guard. It deliberately skips the `api` group: Pusher/Reverb
    // clients expect the raw `{auth}` body, not the success envelope.
    ->withBroadcasting(
        __DIR__ . '/../routes/channels.php',
        ['prefix' => 'api/v1', 'middleware' => ['auth:sanctum', 'active.user', 'throttle:api']],
    )
    ->withSchedule(function (Schedule $schedule): void {
        // Hourly, so each run only sees one hour of expiries. The sweeps
        // queue their work in batches and are ShouldBeUnique, which is what
        // stops overlap: withoutOverlapping() on a queued job only guards
        // the dispatch.
        $schedule->job(new ExpireOldAdsJob)
            ->hourlyAt(0)
            ->name('ads.expire-old');

        // Half an hour after the ads sweep so the ad-status invariants the
        // offer rules depend on (offers belong to ACTIVE ads) have already
        // settled when offers are flipped to EXPIRED.
        $schedule->job(new ExpireOldOffersJob)
            ->hourlyAt(30)
            ->name('offers.expire-old');

        $schedule->job(new ReleaseDueEscrowOrdersJob)
            ->hourlyAt(45)
            ->name('orders.release-due-escrow');

        $schedule->job(new SyncAdViewCountsJob)
            ->cron(sprintf('*/%d * * * *', max(1, (int) config('qbazaar.search.views_sync_minutes'))))
            ->name('search.sync-view-counts')
            ->withoutOverlapping();

        $schedule->job(new ReconcileLedgerJob)
            ->dailyAt('02:30')
            ->timezone('Asia/Qatar')
            ->name('ledger.reconcile');

        $schedule->job(new SweepDueAccountDeletionsJob)
            ->dailyAt('03:00')
            ->timezone('Asia/Qatar')
            ->name('accounts.sweep-deletions')
            ->withoutOverlapping();

        $schedule->command('model:prune', ['--model' => [DataExport::class]])
            ->dailyAt('03:15')
            ->timezone('Asia/Qatar')
            ->name('accounts.prune-data-exports')
            ->withoutOverlapping();

        $schedule->command('model:prune', ['--model' => [OtpCode::class, TrustedDevice::class]])
            ->dailyAt('03:00')
            ->timezone('Asia/Qatar')
            ->name('auth.prune')
            ->withoutOverlapping();

        // Every refresh mints a new access and refresh token, so tokens pile
        // up fastest; pruning hourly keeps each run to an hour's worth.
        $schedule->command('sanctum:prune-expired', ['--hours' => (int) config('qbazaar.retention.expired_tokens_hours')])
            ->hourly()
            ->name('auth.prune-access-tokens')
            ->withoutOverlapping();

        $schedule->command('model:prune', ['--model' => [RefreshToken::class]])
            ->hourlyAt(5)
            ->name('auth.prune-refresh-tokens')
            ->withoutOverlapping();

        $schedule->command('model:prune', ['--model' => [RecentView::class, DatabaseNotification::class]])
            ->dailyAt('03:30')
            ->timezone('Asia/Qatar')
            ->name('retention.prune-history')
            ->withoutOverlapping();

        $schedule->command('activitylog:clean', ['--force' => true])
            ->dailyAt('03:45')
            ->timezone('Asia/Qatar')
            ->name('retention.clean-activity-log')
            ->withoutOverlapping();

        $schedule->command('queue:prune-failed', ['--hours' => (int) config('qbazaar.retention.failed_jobs_hours')])
            ->dailyAt('04:00')
            ->timezone('Asia/Qatar')
            ->name('retention.prune-failed-jobs')
            ->withoutOverlapping();

        $schedule->command('auth:clear-resets')
            ->everyFifteenMinutes()
            ->name('auth.clear-password-resets')
            ->withoutOverlapping();

        $schedule->command('horizon:snapshot')
            ->everyFiveMinutes()
            ->name('horizon.snapshot');

        // Requests only read the home feed and the category/place counters;
        // this keeps them at most a couple of minutes old.
        $schedule->job(new WarmCatalogCacheJob)
            ->cron(sprintf('*/%d * * * *', max(1, (int) config('qbazaar.catalog.warm_every_minutes'))))
            ->name('catalog.warm-cache')
            ->withoutOverlapping();

        $schedule->job(new FlushAdViewCountsJob)
            ->everyMinute()
            ->name('ads.flush-view-counts')
            ->withoutOverlapping();

        $schedule->job(new ExpirePromotionsJob)
            ->cron(sprintf('*/%d * * * *', max(1, (int) config('qbazaar.promotions.expire_every_minutes'))))
            ->name('promotions.expire');
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // Aliases so route files can use 'locale', 'api.wrap', 'track.client'.
        // `active.user` and `phone.verified` must be listed AFTER `auth:sanctum`
        // in any route group — they assume $request->user() is already set.
        $middleware->alias([
            'locale' => LocaleMiddleware::class,
            'api.wrap' => ApiResponseWrapper::class,
            'track.client' => TrackClient::class,
            'active.user' => EnsureUserIsActive::class,
            'phone.verified' => EnsurePhoneVerified::class,
            'idempotent' => Idempotent::class,
            'staff' => EnsureStaff::class,
            'permission' => PermissionMiddleware::class,
            'turnstile' => VerifyTurnstile::class,
        ]);

        // Proxies come from config/trustedproxy.php. Forwarded Host and Port are
        // not trusted: Cloudflare passes a client's own values through.
        $middleware->trustProxies(headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO);

        // API group — every /api/v1/* request runs through these in order
        $middleware->api(prepend: [
            TrackClient::class,
            LocaleMiddleware::class,
            ApiResponseWrapper::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Render exceptions to our JSON error envelope for /api/* and Accept: application/json clients
        $exceptions->shouldRenderJsonWhen(function (Request $request) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            return jsonError(
                ErrorCode::VALIDATION_FAILED,
                __(ErrorCode::VALIDATION_FAILED->messageKey()),
                $e->errors(),
                $request->header('X-Request-Id'),
            );
        });

        $exceptions->render(function (DomainException $e, Request $request) {
            if ($request->is('admin/*') && ! $request->expectsJson()) {
                return back()->with('error', $e->getMessage());
            }

            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            return jsonError(
                $e->errorCode,
                $e->getMessage(),
                $e->details,
                $request->header('X-Request-Id'),
            );
        });

        // The framework turns a failed policy or gate (AuthorizationException)
        // into AccessDeniedHttpException before these renderers run.
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            // Policies / gates failing return our generic 403 envelope.
            // Domain rules with specific ErrorCodes (USER_002, USER_003, …)
            // should still throw DomainException so they keep their stable
            // codes; this branch is the catch-all "you don't own this".
            return jsonError(
                ErrorCode::FORBIDDEN,
                $e->getMessage() !== '' ? $e->getMessage() : __(ErrorCode::FORBIDDEN->messageKey()),
                requestId: $request->header('X-Request-Id'),
            );
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            return jsonError(
                ErrorCode::AUTH_TOKEN_INVALID,
                __(ErrorCode::AUTH_TOKEN_INVALID->messageKey()),
                requestId: $request->header('X-Request-Id'),
            );
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            $code = NotFoundErrorCode::for($e);

            return jsonError(
                $code,
                __($code->messageKey()),
                requestId: $request->header('X-Request-Id'),
            );
        });

        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            return jsonError(
                ErrorCode::RATE_LIMIT_EXCEEDED,
                __(ErrorCode::RATE_LIMIT_EXCEEDED->messageKey()),
                requestId: $request->header('X-Request-Id'),
            );
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            // Fallback for any other HTTP-shaped exception we haven't matched above
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => ErrorCode::SERVER_ERROR->value,
                    'message_key' => ErrorCode::SERVER_ERROR->messageKey(),
                    'message' => $e->getMessage() ?: __(ErrorCode::SERVER_ERROR->messageKey()),
                    'details' => null,
                    'request_id' => $request->header('X-Request-Id'),
                ],
            ], $e->getStatusCode());
        });
    })->create();
