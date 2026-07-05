<?php

declare(strict_types=1);

use App\Enums\Language;
use App\Http\Controllers\Manage\AdController;
use App\Http\Controllers\Manage\AuthController;
use App\Http\Controllers\Manage\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Custom admin panel (/manage) — Tailwind, session-authenticated
|--------------------------------------------------------------------------
| Session (web guard) auth, gated to staff roles by the `staff` middleware.
| Runs alongside the legacy Filament panel (/admin) during the migration.
*/
Route::prefix('manage')->name('manage.')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.attempt');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware('staff')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('ads', [AdController::class, 'index'])->name('ads.index');
        Route::get('ads/{ad}', [AdController::class, 'show'])->name('ads.show');
        Route::post('ads/{ad}/approve', [AdController::class, 'approve'])->name('ads.approve');
        Route::post('ads/{ad}/reject', [AdController::class, 'reject'])->name('ads.reject');
        Route::post('ads/{ad}/suspend', [AdController::class, 'suspend'])->name('ads.suspend');
        Route::post('ads/{ad}/unsuspend', [AdController::class, 'unsuspend'])->name('ads.unsuspend');
        Route::post('ads/{ad}/feature', [AdController::class, 'toggleFeature'])->name('ads.feature');
    });
});

/*
|--------------------------------------------------------------------------
| Admin locale switch
|--------------------------------------------------------------------------
| Persists the signed-in staff member's preferred UI language, then bounces
| back. LocaleMiddleware reads the same `language` column on the next request,
| so the whole Filament panel (our strings + Filament's bundled translations)
| re-renders in the chosen language. Session-guarded so only an authenticated
| panel user can change their own preference.
*/
Route::middleware(['web', 'auth'])
    ->get('/admin/locale/{locale}', function (string $locale) {
        abort_unless(in_array($locale, ['ar', 'en'], true), 404);
        auth()->user()?->forceFill(['language' => Language::from($locale)])->save();

        return redirect()->back();
    })
    ->name('admin.locale');

/*
|--------------------------------------------------------------------------
| Swagger UI — single source of API docs
|--------------------------------------------------------------------------
| Loads the OpenAPI spec from qbazaar-contracts/openapi/v1.yaml (served by
| /api/v1/openapi.yaml) and renders Swagger UI from CDN.
| Available at /swagger and the canonical /docs.
*/
Route::view('/swagger', 'swagger')->name('swagger.ui');
Route::view('/docs', 'swagger')->name('docs');
