<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Auth\Turnstile\TurnstileVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

final class FakeTurnstileVerifier implements TurnstileVerifier
{
    /** @var list<array{token: string, ip: ?string}> */
    public array $calls = [];

    public function __construct(private readonly string $validToken) {}

    public function verify(string $token, ?string $remoteIp): bool
    {
        $this->calls[] = ['token' => $token, 'ip' => $remoteIp];

        return $token === $this->validToken;
    }
}

beforeEach(function (): void {
    Cache::flush();
    Notification::fake();

    config(['services.turnstile.enabled' => true]);

    $this->verifier = new FakeTurnstileVerifier('valid-token');
    app()->instance(TurnstileVerifier::class, $this->verifier);
});

dataset('protected auth requests', [
    'register' => ['/api/v1/auth/register', [
        'full_name' => 'Ahmed Al-Ali',
        'email' => 'ahmed@example.qa',
        'phone' => '+97455123456',
        'password' => 'Str0ng!Pass',
        'account_type' => 'private',
        'language' => 'ar',
        'accepted_terms' => true,
    ]],
    'send otp' => ['/api/v1/auth/send-otp', ['phone' => '+97455123456']],
    'resend otp' => ['/api/v1/auth/resend-otp', ['phone' => '+97455123456']],
    'send email code' => ['/api/v1/auth/email-otp/send', ['email' => 'ahmed@example.qa']],
]);

it('rejects a request without a turnstile token', function (string $uri, array $payload): void {
    postJson($uri, $payload)
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'TURNSTILE_001')
        ->assertJsonPath('error.message_key', 'errors.turnstile.failed');

    expect($this->verifier->calls)->toBe([]);
    Notification::assertNothingSent();
})->with('protected auth requests');

it('rejects a request with a token the verifier refuses', function (string $uri, array $payload): void {
    postJson($uri, $payload, ['X-Turnstile-Token' => 'forged'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'TURNSTILE_001');

    Notification::assertNothingSent();
})->with('protected auth requests');

it('rejects an oversized token without calling the verifier', function (): void {
    postJson('/api/v1/auth/send-otp', ['phone' => '+97455123456'], ['X-Turnstile-Token' => str_repeat('a', 2049)])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'TURNSTILE_001');

    expect($this->verifier->calls)->toBe([]);
});

it('registers when the token is valid and passes the client ip to the verifier', function (): void {
    postJson('/api/v1/auth/register', [
        'full_name' => 'Ahmed Al-Ali',
        'email' => 'ahmed@example.qa',
        'phone' => '+97455123456',
        'password' => 'Str0ng!Pass',
        'account_type' => 'private',
        'language' => 'ar',
        'accepted_terms' => true,
    ], ['X-Turnstile-Token' => 'valid-token'])->assertCreated();

    expect(User::query()->where('email', 'ahmed@example.qa')->exists())->toBeTrue()
        ->and($this->verifier->calls)->toBe([['token' => 'valid-token', 'ip' => '127.0.0.1']]);
});

it('sends an email sign-in code when the token is valid', function (): void {
    postJson('/api/v1/auth/email-otp/send', ['email' => 'ahmed@example.qa'], ['X-Turnstile-Token' => 'valid-token'])
        ->assertStatus(202);
});

it('sends an otp when the token is valid', function (): void {
    postJson('/api/v1/auth/send-otp', ['phone' => '+97455123456'], ['X-Turnstile-Token' => 'valid-token'])
        ->assertStatus(202);
});

it('skips the check entirely when turnstile is disabled', function (): void {
    config(['services.turnstile.enabled' => false]);

    postJson('/api/v1/auth/send-otp', ['phone' => '+97455123456'])->assertStatus(202);

    expect($this->verifier->calls)->toBe([]);
});
