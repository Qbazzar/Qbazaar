<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
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
// Sprint endpoints land here, one Route group per domain.
// ────────────────────────────────────────────────────────────────────────────
