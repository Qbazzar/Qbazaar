<?php

declare(strict_types=1);

use App\Enums\Language;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
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
