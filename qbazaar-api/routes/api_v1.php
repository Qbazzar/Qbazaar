<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Account\AccountSummaryController;
use App\Http\Controllers\Api\V1\Account\BlockedUsersController;
use App\Http\Controllers\Api\V1\Account\DataExportController;
use App\Http\Controllers\Api\V1\Account\DeactivateAccountController;
use App\Http\Controllers\Api\V1\Account\DeleteAccountController;
use App\Http\Controllers\Api\V1\Account\PasswordController;
use App\Http\Controllers\Api\V1\Account\PrivacySettingsController;
use App\Http\Controllers\Api\V1\Account\ProfileController;
use App\Http\Controllers\Api\V1\Account\SessionsController;
use App\Http\Controllers\Api\V1\Account\VerificationStatusController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\OtpController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Auth\RefreshTokenController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
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
|   Sprint 10 → reports/*, notifications/*, device-tokens/*
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

        // Authenticated — block / unblock
        Route::middleware(['auth:sanctum', 'active.user', 'throttle:api'])->group(function (): void {
            Route::post('/{user}/block', [BlockController::class, 'store'])->name('block.store');
            Route::delete('/{user}/block', [BlockController::class, 'destroy'])->name('block.destroy');
        });
    });
