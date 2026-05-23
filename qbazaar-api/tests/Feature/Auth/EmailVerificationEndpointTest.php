<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();

    $this->user = User::factory()->create([
        'email' => 'verify@example.qa',
        'email_verified' => false,
    ]);
});

/**
 * Helper: mint a signed verify-email URL the same way the
 * EmailVerificationNotification does.
 */
function signedVerifyEmailUrl(User $user, ?int $minutes = null): string
{
    return URL::temporarySignedRoute(
        'api.v1.auth.verify-email',
        Carbon::now()->addMinutes($minutes ?? (int) config('auth.verification.expire', 60)),
        [
            'id' => $user->getKey(),
            'hash' => sha1($user->email),
        ],
    );
}

it('verifies the email on a valid signed URL and returns 200', function (): void {
    $url = signedVerifyEmailUrl($this->user);

    getJson($url)
        ->assertOk()
        ->assertJson(
            fn ($json) => $json
                ->where('success', true)
                ->where('data.email_verified', true)
                ->where('data.message_key', 'messages.auth.email_verified')
                ->etc(),
        );

    expect($this->user->fresh()->email_verified)->toBeTrue();
});

it('is idempotent: a second hit on the same signed URL still returns 200', function (): void {
    $url = signedVerifyEmailUrl($this->user);

    getJson($url)->assertOk();

    // The user is now verified — the same URL should still 200, with the
    // "already verified" message_key.
    getJson($url)
        ->assertOk()
        ->assertJson(
            fn ($json) => $json
                ->where('data.email_verified', true)
                ->where('data.message_key', 'messages.auth.email_already_verified')
                ->etc(),
        );
});

it('rejects an unsigned URL with a 403 via the signed middleware', function (): void {
    $unsigned = route('api.v1.auth.verify-email', [
        'id' => $this->user->getKey(),
        'hash' => sha1($this->user->email),
    ]);

    getJson($unsigned)->assertStatus(403);

    expect($this->user->fresh()->email_verified)->toBeFalse();
});

it('rejects a URL whose signature was tampered with', function (): void {
    $url = signedVerifyEmailUrl($this->user);

    // Flip the last character of the signature query parameter.
    $tampered = preg_replace_callback('/(signature=)([^&]+)/', function (array $m): string {
        $sig = $m[2];
        $last = substr($sig, -1);
        $replacement = $last === 'a' ? 'b' : 'a';

        return $m[1] . substr($sig, 0, -1) . $replacement;
    }, $url);

    getJson((string) $tampered)->assertStatus(403);

    expect($this->user->fresh()->email_verified)->toBeFalse();
});

it('rejects an expired signed URL', function (): void {
    // Mint with 1-minute expiry, then jump 5 minutes forward in time.
    $url = signedVerifyEmailUrl($this->user, minutes: 1);

    Carbon::setTestNow(Carbon::now()->addMinutes(5));

    getJson($url)->assertStatus(403);

    Carbon::setTestNow(); // reset

    expect($this->user->fresh()->email_verified)->toBeFalse();
});

it('rejects when the hash does not match the user', function (): void {
    $url = URL::temporarySignedRoute(
        'api.v1.auth.verify-email',
        Carbon::now()->addMinutes(60),
        [
            'id' => $this->user->getKey(),
            'hash' => sha1('not-' . $this->user->email),
        ],
    );

    getJson($url)
        ->assertStatus(401)
        ->assertJson(
            fn ($json) => $json
                ->where('error.code', 'AUTH_010')
                ->etc(),
        );

    expect($this->user->fresh()->email_verified)->toBeFalse();
});
