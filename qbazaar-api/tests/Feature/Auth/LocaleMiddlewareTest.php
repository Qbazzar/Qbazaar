<?php

declare(strict_types=1);

use App\Enums\Language;
use App\Http\Middleware\LocaleMiddleware;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

uses(RefreshDatabase::class);

/**
 * Regression: the `language` column is cast to the Language enum, so the
 * middleware must normalise to its string value before matching the supported
 * list — a strict in_array() of an enum against ['ar','en'] silently never
 * matched and the user's preference (incl. the admin AR/EN switch) was ignored.
 */
function runLocale(?User $user, ?string $queryLang = null, string $acceptLanguage = 'xx'): string
{
    $uri = $queryLang !== null ? "/admin?lang={$queryLang}" : '/admin';
    // Pin Accept-Language to an unsupported tag by default so it doesn't shadow
    // the branch under test (Request::create injects a real header otherwise).
    $request = Request::create($uri, server: ['HTTP_ACCEPT_LANGUAGE' => $acceptLanguage]);
    $request->setUserResolver(static fn () => $user);

    App::setLocale('zz'); // neutral sentinel so each assertion proves a real change
    (new LocaleMiddleware)->handle($request, static fn () => response('ok'));

    return App::getLocale();
}

it('applies the authenticated user Arabic preference from the enum cast', function (): void {
    $user = User::factory()->make(['language' => Language::ARABIC->value]);

    expect(runLocale($user))->toBe('ar');
});

it('applies the authenticated user English preference from the enum cast', function (): void {
    $user = User::factory()->make(['language' => Language::ENGLISH->value]);

    expect(runLocale($user))->toBe('en');
});

it('lets the ?lang query override the user preference', function (): void {
    $user = User::factory()->make(['language' => Language::ENGLISH->value]);

    expect(runLocale($user, 'ar'))->toBe('ar');
});

it('falls back to the default language for a guest', function (): void {
    expect(runLocale(null))->toBe((string) config('qbazaar.default_language'));
});

it('answers in the language saved on the token owner', function (string $language, string $message): void {
    $user = User::factory()->create(['language' => $language]);
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->withHeader('Accept-Language', $language === 'ar' ? 'en' : 'ar')
        ->getJson('/api/v1/conversations/01HZZZZZZZZZZZZZZZZZZZZZZZ')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'MSG_004')
        ->assertJsonPath('error.message', $message);
})->with([
    'arabic' => ['ar', 'المحادثة غير موجودة.'],
    'english' => ['en', 'Conversation not found.'],
]);

it('lets a guest pick the language with Accept-Language', function (): void {
    $this->withHeader('Accept-Language', 'en-US,en;q=0.9')
        ->getJson('/api/v1/ads/01HZZZZZZZZZZZZZZZZZZZZZZZ')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'AD_001')
        ->assertJsonPath('error.message', __('errors.ad.not.found', [], 'en'));
});
