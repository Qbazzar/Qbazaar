<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Account\AccountSummaryController;
use App\Http\Controllers\Api\V1\Account\BlockedUsersController;
use App\Http\Controllers\Api\V1\Account\DataExportController;
use App\Http\Controllers\Api\V1\Account\DeactivateAccountController;
use App\Http\Controllers\Api\V1\Account\DeleteAccountController;
use App\Http\Controllers\Api\V1\Account\DeviceTokenController;
use App\Http\Controllers\Api\V1\Account\NotificationsController;
use App\Http\Controllers\Api\V1\Account\PasswordController;
use App\Http\Controllers\Api\V1\Account\PrivacySettingsController;
use App\Http\Controllers\Api\V1\Account\ProfileController;
use App\Http\Controllers\Api\V1\Account\SessionsController;
use App\Http\Controllers\Api\V1\Account\VerificationStatusController;
use App\Http\Controllers\Api\V1\Ads\AdController;
use App\Http\Controllers\Api\V1\Ads\AdImageController;
use App\Http\Controllers\Api\V1\Ads\FeaturedAdsController;
use App\Http\Controllers\Api\V1\Ads\MarkSoldController;
use App\Http\Controllers\Api\V1\Ads\PublishAdController;
use App\Http\Controllers\Api\V1\Ads\RenewAdController;
use App\Http\Controllers\Api\V1\Ads\SimilarAdsController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\OtpController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Auth\RefreshTokenController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Cms\PageController;
use App\Http\Controllers\Api\V1\Favorites\FavoriteController;
use App\Http\Controllers\Api\V1\Help\HelpController;
use App\Http\Controllers\Api\V1\Media\MediaOriginalController;
use App\Http\Controllers\Api\V1\Messaging\ConversationController;
use App\Http\Controllers\Api\V1\Messaging\MessageController;
use App\Http\Controllers\Api\V1\Offers\OfferController;
use App\Http\Controllers\Api\V1\Recents\RecentViewController;
use App\Http\Controllers\Api\V1\Reference\CategoryController;
use App\Http\Controllers\Api\V1\Reference\LocationController;
use App\Http\Controllers\Api\V1\Reports\ReportsController;
use App\Http\Controllers\Api\V1\Reviews\ReviewController;
use App\Http\Controllers\Api\V1\Search\SavedSearchController;
use App\Http\Controllers\Api\V1\Search\SearchController;
use App\Http\Controllers\Api\V1\Support\SupportController;
use App\Http\Controllers\Api\V1\Uploads\AvatarUploadController;
use App\Http\Controllers\Api\V1\Users\BlockController;
use App\Http\Controllers\Api\V1\Users\PublicProfileController;
use App\Http\Controllers\Api\V1\Users\UserAdsController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| QBazaar API — v1
|--------------------------------------------------------------------------
|
| All paths here are mounted under `/api/v1/`. The API middleware group
| (TrackClient + LocaleMiddleware + ApiResponseWrapper) is wired in
| bootstrap/app.php and runs automatically.
|
| Endpoints are added per sprint:
|   Sprint 1  → auth/*
|   Sprint 2  → account/*, users/*
|   Sprint 3  → categories/*, locations/*
|   Sprint 4  → uploads/*, media/*
|   Sprint 5  → ads/*
|   Sprint 6  → search, saved-searches/*
|   Sprint 7  → favorites/*
|   Sprint 8  → conversations/*, messages/*
|   Sprint 9  → offers/*
|   Sprint 10 → reports/*, account/notifications/*
|   Sprint 12 → cms/*, support/*
*/

// ────────────────────────────────────────────────────────────────────────────
// Health — used by Sprint 0 verification, CI smoke tests, and uptime monitors
// ────────────────────────────────────────────────────────────────────────────
Route::get('/health', function (): JsonResponse {
    return response()->json([
        'status' => 'ok',
        'version' => config('app.version', '1.0.0'),
        'timestamp' => now()->toIso8601String(),
    ]);
})->name('api.v1.health');

// ────────────────────────────────────────────────────────────────────────────
// OpenAPI raw spec — served from qbazaar-contracts/openapi/v1.yaml so the spec
// stays the single source of truth. Used by Swagger UI (see web.php /swagger).
// ────────────────────────────────────────────────────────────────────────────
Route::get('/openapi.yaml', function (): Response {
    $path = base_path('../qbazaar-contracts/openapi/v1.yaml');
    abort_unless(is_file($path), 404, 'openapi/v1.yaml not found in qbazaar-contracts/');

    return response((string) file_get_contents($path), 200, [
        'Content-Type' => 'application/yaml; charset=utf-8',
        'Cache-Control' => 'public, max-age=60',
    ]);
})->name('api.v1.openapi');

// ────────────────────────────────────────────────────────────────────────────
// Sprint endpoints land here, one Route group per domain.
// ────────────────────────────────────────────────────────────────────────────

// ── Sprint 1 — Auth ─────────────────────────────────────────────────────────
//   Wave 1: register / login / logout / refresh
//   Wave 2: OTP (send/verify/resend), password reset, email verification
Route::prefix('auth')->name('api.v1.auth.')->group(function (): void {
    Route::post('/register', RegisterController::class)
        ->middleware('throttle:auth')
        ->name('register');

    Route::post('/login', LoginController::class)
        ->middleware('throttle:auth')
        ->name('login');

    Route::post('/logout', LogoutController::class)
        ->middleware('auth:sanctum')
        ->name('logout');

    Route::post('/refresh', RefreshTokenController::class)
        ->middleware('throttle:auth')
        ->name('refresh');

    // OTP — phone verification (Wave 2)
    Route::post('/send-otp', [OtpController::class, 'send'])
        ->middleware('throttle:otp')
        ->name('send-otp');

    Route::post('/verify-otp', [OtpController::class, 'verify'])
        ->middleware('throttle:otp')
        ->name('verify-otp');

    Route::post('/resend-otp', [OtpController::class, 'resend'])
        ->middleware('throttle:otp')
        ->name('resend-otp');

    // Password reset (Wave 2)
    Route::post('/forgot-password', [PasswordResetController::class, 'forgot'])
        ->middleware('throttle:auth')
        ->name('forgot-password');

    Route::post('/reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:auth')
        ->name('reset-password');

    // Helper alias used by PasswordResetNotification when it can't reach the
    // frontend URL — points clients at an API-signed reset link. Hidden from
    // the public contract.
    Route::get('/password-reset/verify', fn () => response()->noContent())
        ->middleware('signed')
        ->name('password.reset.link');

    // Email verification (Wave 2)
    Route::post('/send-email-verification', [EmailVerificationController::class, 'send'])
        ->middleware('auth:sanctum')
        ->name('send-email-verification');

    Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('verify-email');
});

// ── Sprint 2 — Account & Users ──────────────────────────────────────────────
//   /account/* — the signed-in user managing their own data
//   /users/{user}/* — interactions with other users (public profile, block)
Route::prefix('account')
    ->name('api.v1.account.')
    ->middleware(['auth:sanctum', 'active.user', 'throttle:api'])
    ->group(function (): void {
        Route::get('/summary', AccountSummaryController::class)->name('summary');

        Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

        Route::put('/password', PasswordController::class)->name('password.update');

        Route::get('/sessions', [SessionsController::class, 'index'])->name('sessions.index');
        Route::delete('/sessions/{id}', [SessionsController::class, 'destroy'])->name('sessions.destroy');

        Route::get('/verification-status', VerificationStatusController::class)->name('verification-status');

        Route::get('/privacy-settings', [PrivacySettingsController::class, 'show'])->name('privacy.show');
        Route::put('/privacy-settings', [PrivacySettingsController::class, 'update'])->name('privacy.update');

        Route::get('/blocked-users', BlockedUsersController::class)->name('blocked-users');

        // Web-push device tokens (FCM). DELETE takes the token in the body —
        // FCM tokens are too long (and too sensitive) to put in the URL.
        Route::post('/device-tokens', [DeviceTokenController::class, 'store'])->name('device-tokens.store');
        Route::delete('/device-tokens', [DeviceTokenController::class, 'destroy'])->name('device-tokens.destroy');

        // Account lifecycle (Wave 2) — deactivate / schedule-deletion / data-export.
        Route::post('/deactivate', DeactivateAccountController::class)->name('deactivate');
        Route::delete('/delete-request', DeleteAccountController::class)->name('delete-request');
        Route::post('/data-export-request', [DataExportController::class, 'request'])
            ->name('data-export-request');
    });

// Signed download URL for a previously-generated data export.
// Kept outside the `account` group so `signed` is the only auth check we
// need on a one-shot link emailed to the user. We still require an
// authenticated user via `auth:sanctum` on top of the signature so a
// leaked URL alone is not enough.
Route::middleware(['signed', 'auth:sanctum', 'active.user'])
    ->get('/account/data-export/{id}', [DataExportController::class, 'download'])
    ->name('api.v1.account.data-export.download');

// Uploads (Sprint 2 Wave 2 ships avatar; Sprint 4 will add the ad-image
// pipeline alongside).
Route::prefix('uploads')
    ->name('api.v1.uploads.')
    ->middleware(['auth:sanctum', 'active.user', 'throttle:api'])
    ->group(function (): void {
        Route::post('/avatar', AvatarUploadController::class)->name('avatar');
    });

// ── Sprint 3 — Categories & Locations ───────────────────────────────────────
//   Public reference data — browse taxonomy + Qatar location tree. No auth.
//   All endpoints are cached at the controller level (see Reference\*).
Route::prefix('categories')
    ->name('api.v1.categories.')
    ->middleware('throttle:api')
    ->group(function (): void {
        Route::get('tree', [CategoryController::class, 'tree'])->name('tree');
        Route::get('main', [CategoryController::class, 'main'])->name('main');
        Route::get('{slug}/stats', [CategoryController::class, 'stats'])->name('stats');
        Route::get('{slug}/filters', [CategoryController::class, 'filters'])->name('filters');
        Route::get('{slug}/fields', [CategoryController::class, 'fields'])->name('fields');
    });

Route::prefix('locations')
    ->name('api.v1.locations.')
    ->middleware('throttle:api')
    ->group(function (): void {
        Route::get('qatar', [LocationController::class, 'qatar'])->name('qatar');
    });

// ── Sprint 4 + 5 Wave A — Ads ───────────────────────────────────────────────
//   Public:
//     GET    /ads               — paginated feed of active ads
//     GET    /ads/{id}          — single ad detail (public visibility rules)
//     GET    /media/{media}/original   — original image (signed, expiring)
//   Authenticated:
//     POST   /ads               — create draft
//     PUT    /ads/{id}          — owner update
//     DELETE /ads/{id}          — owner soft-delete
//     POST   /ads/{id}/publish  — draft → active (throttle:publish)
//     POST   /ads/{id}/mark-sold
//     POST   /ads/{id}/renew
//     POST   /ads/{ad}/images          — multipart image upload
//     POST   /ads/{ad}/images/reorder
//     DELETE /media/{media}            — remove a single image
//     GET    /account/ads              — caller's own ads (every status)
Route::prefix('ads')
    ->name('api.v1.ads.')
    ->middleware('throttle:api')
    ->group(function (): void {
        Route::get('/', [AdController::class, 'index'])->name('index');

        // Static-segment routes MUST be declared before the {id} catch-all
        // so Laravel's router doesn't match `featured` as an ad ULID.
        Route::get('/featured', FeaturedAdsController::class)->name('featured');

        Route::get('/{id}', [AdController::class, 'show'])->name('show');
        Route::get('/{id}/similar', SimilarAdsController::class)->name('similar');
    });

// Original-resolution image download. Public content (ad images need no
// auth) but `signed`: MediaResource embeds a temporary signed URL so
// originals expire instead of becoming permanent hotlinkable links. The
// sizes.* conversion URLs remain plain public URLs.
Route::get('/media/{media}/original', MediaOriginalController::class)
    ->middleware(['signed', 'throttle:api'])
    ->name('api.v1.media.original');

Route::middleware(['auth:sanctum', 'active.user'])->group(function (): void {
    Route::post('/ads', [AdController::class, 'store'])
        ->middleware('throttle:publish')
        ->name('api.v1.ads.store');

    Route::put('/ads/{id}', [AdController::class, 'update'])
        ->middleware('throttle:api')
        ->name('api.v1.ads.update');

    Route::delete('/ads/{id}', [AdController::class, 'destroy'])
        ->middleware('throttle:api')
        ->name('api.v1.ads.destroy');

    Route::post('/ads/{id}/publish', PublishAdController::class)
        ->middleware(['throttle:publish', 'idempotent'])
        ->name('api.v1.ads.publish');

    Route::post('/ads/{id}/mark-sold', MarkSoldController::class)
        ->middleware('throttle:api')
        ->name('api.v1.ads.mark-sold');

    Route::post('/ads/{id}/renew', RenewAdController::class)
        ->middleware('throttle:api')
        ->name('api.v1.ads.renew');

    Route::post('/ads/{ad}/reviews', [ReviewController::class, 'store'])
        ->middleware('throttle:api')
        ->name('api.v1.ads.reviews.store');

    Route::post('/ads/{ad}/images', [AdImageController::class, 'store'])
        ->middleware('throttle:api')
        ->name('api.v1.ads.images.store');

    Route::post('/ads/{ad}/images/reorder', [AdImageController::class, 'reorder'])
        ->middleware('throttle:api')
        ->name('api.v1.ads.images.reorder');

    Route::delete('/media/{media}', [AdImageController::class, 'destroy'])
        ->middleware('throttle:api')
        ->name('api.v1.media.destroy');

    Route::get('/account/ads', [AdController::class, 'myAds'])
        ->middleware('throttle:api')
        ->name('api.v1.account.ads.index');
});

// ── Sprint 6 — Search ───────────────────────────────────────────────────────
//   Public:
//     GET  /search              — paginated keyword + filter search + facets
//     GET  /search/suggestions  — prefix-match title suggestions (cached)
//   Authenticated (account group):
//     GET    /account/saved-searches        — list (cap 10/user)
//     POST   /account/saved-searches        — create
//     DELETE /account/saved-searches/{id}   — remove
Route::prefix('search')
    ->name('api.v1.search.')
    ->middleware('throttle:api')
    ->group(function (): void {
        Route::get('/', [SearchController::class, 'index'])->name('index');
        Route::get('/suggestions', [SearchController::class, 'suggestions'])->name('suggestions');
    });

Route::prefix('account/saved-searches')
    ->name('api.v1.account.saved-searches.')
    ->middleware(['auth:sanctum', 'active.user', 'throttle:api'])
    ->group(function (): void {
        Route::get('/', [SavedSearchController::class, 'index'])->name('index');
        Route::post('/', [SavedSearchController::class, 'store'])->name('store');
        Route::delete('/{id}', [SavedSearchController::class, 'destroy'])->name('destroy');
    });

// ── Sprint 7 — Favorites & Recently Viewed ──────────────────────────────────
//   Authenticated:
//     POST   /ads/{id}/favorite           — toggle favourite (returns state + count)
//     GET    /account/favorites           — paginated list of caller's favourites
//     GET    /account/recently-viewed     — paginated history (auth-only)
//     DELETE /account/recently-viewed     — clear caller's history
//   Public-ish:
//     POST   /ads/{id}/view               — track a view (auth user OR X-Session-Id)
Route::middleware(['auth:sanctum', 'active.user', 'throttle:api'])->group(function (): void {
    Route::post('/ads/{id}/favorite', [FavoriteController::class, 'toggle'])
        ->name('api.v1.ads.favorite.toggle');

    Route::get('/account/favorites', [FavoriteController::class, 'index'])
        ->name('api.v1.account.favorites.index');

    Route::get('/account/recently-viewed', [RecentViewController::class, 'index'])
        ->name('api.v1.account.recently-viewed.index');

    Route::delete('/account/recently-viewed', [RecentViewController::class, 'destroy'])
        ->name('api.v1.account.recently-viewed.destroy');
});

Route::post('/ads/{id}/view', [RecentViewController::class, 'track'])
    ->middleware(['throttle:api'])
    ->name('api.v1.ads.view');

// ── Sprint 8 Wave A — Messaging ─────────────────────────────────────────────
//   Authenticated:
//     POST   /conversations                       — start / resolve a thread
//     GET    /conversations                       — paginated inbox
//     GET    /conversations/unread-count          — header badge
//     GET    /conversations/{id}                  — full thread
//     GET    /conversations/{id}/messages         — cursor transcript
//     POST   /conversations/{id}/messages         — append + broadcast
//     POST   /conversations/{id}/read             — mark all read
Route::middleware(['auth:sanctum', 'active.user'])->group(function (): void {
    Route::post('/conversations', [ConversationController::class, 'store'])
        ->name('api.v1.conversations.store');

    Route::get('/conversations', [ConversationController::class, 'index'])
        ->name('api.v1.conversations.index');

    Route::get('/conversations/unread-count', [ConversationController::class, 'unreadCount'])
        ->name('api.v1.conversations.unread-count');

    Route::get('/conversations/{id}', [ConversationController::class, 'show'])
        ->name('api.v1.conversations.show');

    Route::get('/conversations/{id}/messages', [MessageController::class, 'index'])
        ->name('api.v1.conversations.messages.index');

    Route::post('/conversations/{id}/messages', [MessageController::class, 'store'])
        ->middleware('throttle:messages')
        ->name('api.v1.conversations.messages.store');

    Route::post('/conversations/{id}/read', [ConversationController::class, 'markRead'])
        ->name('api.v1.conversations.read');
});

// ── Sprint 9 — Offers ───────────────────────────────────────────────────────
//   Authenticated:
//     POST   /conversations/{id}/offers       — buyer creates an offer
//     GET    /conversations/{id}/offers       — list offers in this thread
//     POST   /offers/{id}/accept              — seller accepts (PENDING only)
//     POST   /offers/{id}/reject              — seller rejects (PENDING only)
//     POST   /offers/{id}/withdraw            — buyer withdraws (PENDING only)
Route::middleware(['auth:sanctum', 'active.user'])->group(function (): void {
    Route::post('/conversations/{id}/offers', [OfferController::class, 'store'])
        ->name('api.v1.conversations.offers.store');

    Route::get('/conversations/{id}/offers', [OfferController::class, 'index'])
        ->name('api.v1.conversations.offers.index');

    Route::post('/offers/{id}/accept', [OfferController::class, 'accept'])
        ->name('api.v1.offers.accept');

    Route::post('/offers/{id}/reject', [OfferController::class, 'reject'])
        ->name('api.v1.offers.reject');

    Route::post('/offers/{id}/withdraw', [OfferController::class, 'withdraw'])
        ->name('api.v1.offers.withdraw');
});

// ── Sprint 10 — Notifications inbox ─────────────────────────────────────────
//   Authenticated, scoped to the caller. Mounted under `/account/*` so the
//   ownership invariant is obvious from the URL ("MY notifications").
Route::prefix('account/notifications')
    ->name('api.v1.account.notifications.')
    ->middleware(['auth:sanctum', 'active.user', 'throttle:api'])
    ->group(function (): void {
        Route::get('/', [NotificationsController::class, 'index'])->name('index');
        Route::get('/unread-count', [NotificationsController::class, 'unreadCount'])->name('unread-count');
        Route::post('/read-all', [NotificationsController::class, 'markAllRead'])->name('read-all');
        Route::post('/{id}/read', [NotificationsController::class, 'markRead'])->name('read');
        Route::delete('/{id}', [NotificationsController::class, 'destroy'])->name('destroy');
    });

// ── Sprint 10 — Reports ─────────────────────────────────────────────────────
//   POST /reports — file an abuse report. Single endpoint; admin-side
//   listing/inspection ships in Sprint 11's Filament resource (intentionally
//   not surfaced in the public API).
Route::post('/reports', [ReportsController::class, 'store'])
    ->middleware(['auth:sanctum', 'active.user', 'throttle:api'])
    ->name('api.v1.reports.store');

Route::prefix('users')
    ->name('api.v1.users.')
    ->group(function (): void {
        // Public — no auth needed
        Route::get('/{user}/public-profile', PublicProfileController::class)
            ->middleware('throttle:api')
            ->name('public-profile');

        Route::get('/{user}/ads', UserAdsController::class)
            ->middleware('throttle:api')
            ->name('ads');

        Route::get('/{user}/reviews', [ReviewController::class, 'index'])
            ->middleware('throttle:api')
            ->name('reviews');

        // Authenticated — block / unblock
        Route::middleware(['auth:sanctum', 'active.user', 'throttle:api'])->group(function (): void {
            Route::post('/{user}/block', [BlockController::class, 'store'])->name('block.store');
            Route::delete('/{user}/block', [BlockController::class, 'destroy'])->name('block.destroy');
        });
    });

// ── Sprint 12 — CMS Pages (public, 1h cached) ──────────────────────────────
Route::prefix('pages')->name('api.v1.pages.')->middleware('throttle:api')->group(function (): void {
    Route::get('/', [PageController::class, 'index'])->name('index');
    Route::get('/{slug}', [PageController::class, 'show'])->name('show');
});

// ── Sprint 12 — Help Center (public) ───────────────────────────────────────
Route::prefix('help')->name('api.v1.help.')->middleware('throttle:api')->group(function (): void {
    Route::get('/categories', [HelpController::class, 'categories'])->name('categories');
    Route::get('/categories/{slug}', [HelpController::class, 'categoryShow'])->name('categories.show');
    Route::get('/articles/{slug}', [HelpController::class, 'articleShow'])->name('articles.show');
    Route::get('/search', [HelpController::class, 'search'])->name('search');
});

// ── Sprint 12 — Support Tickets ────────────────────────────────────────────
// POST /support/tickets accepts anon submissions; the auth-only endpoints
// under /account/support/* manage the caller's tickets + replies. Admin
// staff workflow lives in Filament (Sprint 11 admin panel).
Route::post('/support/tickets', [SupportController::class, 'store'])
    ->middleware('throttle:api')
    ->name('api.v1.support.tickets.store');

Route::prefix('account/support/tickets')
    ->name('api.v1.account.support.tickets.')
    ->middleware(['auth:sanctum', 'active.user', 'throttle:api'])
    ->group(function (): void {
        Route::get('/', [SupportController::class, 'myTickets'])->name('index');
        Route::get('/{id}', [SupportController::class, 'show'])->name('show');
        Route::post('/{id}/reply', [SupportController::class, 'reply'])->name('reply');
    });
