<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Middleware\ApiResponseWrapper;
use App\Http\Middleware\EnsurePhoneVerified;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\Idempotent;
use App\Http\Middleware\LocaleMiddleware;
use App\Http\Middleware\TrackClient;
use App\Jobs\Ads\ExpireOldAdsJob;
use App\Jobs\Offers\ExpireOldOffersJob;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
        // Note: RateLimiter definitions live in AppServiceProvider::boot() because the
        // `then:` closure here only runs when routes are NOT cached. After route:cache,
        // throttle middleware would crash with "Rate limiter [api] is not defined".
    )
    ->withSchedule(function (Schedule $schedule): void {
        // Daily 02:00 Asia/Qatar — quiet local window, runs after most
        // sellers have stopped editing. The job is queued (`onQueue('low')`)
        // so the schedule loop returns immediately.
        $schedule->job(new ExpireOldAdsJob)
            ->dailyAt('02:00')
            ->timezone('Asia/Qatar')
            ->name('ads.expire-old')
            ->withoutOverlapping();

        // Runs 30 minutes after the ads sweep so the ad-status invariants
        // the offer rules depend on (offers belong to ACTIVE ads) have
        // already settled when offers are flipped to EXPIRED.
        $schedule->job(new ExpireOldOffersJob)
            ->dailyAt('02:30')
            ->timezone('Asia/Qatar')
            ->name('offers.expire-old')
            ->withoutOverlapping();
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
        ]);

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

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            // Policies / gates failing return our generic 403 envelope.
            // Domain rules with specific ErrorCodes (USER_002, USER_003, …)
            // should still throw DomainException so they keep their stable
            // codes; this branch is the catch-all "you don't own this".
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message_key' => 'errors.forbidden',
                    'message' => $e->getMessage() !== '' ? $e->getMessage() : __('errors.forbidden'),
                    'details' => null,
                    'request_id' => $request->header('X-Request-Id'),
                ],
            ], 403);
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

            return jsonError(
                ErrorCode::AD_NOT_FOUND,  // generic "not found" — overridden by domain controllers as needed
                __('errors.not_found'),
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
