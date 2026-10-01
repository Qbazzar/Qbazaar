<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Auth\RefreshTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\postJson;
use function Pest\Laravel\withServerVariables;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    Notification::fake();
});

function clientIpSeenBehind(string $remoteAddress, string $forwardedFor): ?string
{
    $request = Request::create('/api/v1/health', server: [
        'REMOTE_ADDR' => $remoteAddress,
        'HTTP_X_FORWARDED_FOR' => $forwardedFor,
    ]);

    return app(TrustProxies::class)->handle($request, fn (Request $trusted) => $trusted->ip());
}

describe('sign-in attempts', function (): void {
    it('limits attempts per account, not per shared address', function (): void {
        config(['qbazaar.auth.rate_limits.attempts_per_minute' => 1]);

        postJson('/api/v1/auth/login', ['identifier' => 'first@example.qa', 'password' => 'wrong'])->assertStatus(401);
        postJson('/api/v1/auth/login', ['identifier' => 'second@example.qa', 'password' => 'wrong'])->assertStatus(401);

        postJson('/api/v1/auth/login', ['identifier' => 'FIRST@example.qa', 'password' => 'wrong'])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMIT_EXCEEDED');
    });

    it('still caps one address trying many accounts', function (): void {
        config(['qbazaar.auth.rate_limits.attempts_per_minute_per_ip' => 2]);

        postJson('/api/v1/auth/login', ['identifier' => 'a@example.qa', 'password' => 'wrong'])->assertStatus(401);
        postJson('/api/v1/auth/login', ['identifier' => 'b@example.qa', 'password' => 'wrong'])->assertStatus(401);
        postJson('/api/v1/auth/login', ['identifier' => 'c@example.qa', 'password' => 'wrong'])->assertStatus(429);
    });
});

describe('refresh', function (): void {
    it('lets many devices behind one address refresh', function (): void {
        config(['qbazaar.auth.rate_limits.refresh_per_minute_per_token' => 1]);
        $service = app(RefreshTokenService::class);

        foreach (User::factory()->count(6)->create() as $user) {
            postJson('/api/v1/auth/refresh', ['refresh_token' => $service->issue($user)->refreshToken])->assertOk();
        }
    });

    it('limits retries of one token', function (): void {
        config(['qbazaar.auth.rate_limits.refresh_per_minute_per_token' => 1]);
        $raw = app(RefreshTokenService::class)->issue(User::factory()->create())->refreshToken;

        postJson('/api/v1/auth/refresh', ['refresh_token' => $raw])->assertOk();
        postJson('/api/v1/auth/refresh', ['refresh_token' => $raw])->assertStatus(429);
    });

    it('caps one address cycling through tokens', function (): void {
        config(['qbazaar.auth.rate_limits.refresh_per_minute_per_ip' => 2]);

        postJson('/api/v1/auth/refresh', ['refresh_token' => 'rt_01aaaaaaaaaaaaaaaaaaaaaaaa1'])->assertStatus(401);
        postJson('/api/v1/auth/refresh', ['refresh_token' => 'rt_01bbbbbbbbbbbbbbbbbbbbbbbb1'])->assertStatus(401);
        postJson('/api/v1/auth/refresh', ['refresh_token' => 'rt_01cccccccccccccccccccccccc1'])->assertStatus(429);
    });
});

describe('proxies', function (): void {
    it('reads the client address that Cloudflare forwards', function (): void {
        expect(clientIpSeenBehind('173.245.48.10', '203.0.113.7'))->toBe('203.0.113.7');
    });

    it('ignores a forwarded address from a client that is not a trusted proxy', function (): void {
        expect(clientIpSeenBehind('198.51.100.20', '203.0.113.7'))->toBe('198.51.100.20');
    });

    it('ignores an address a client prepends before Cloudflare appends its own', function (): void {
        expect(clientIpSeenBehind('173.245.48.10', '10.0.0.1, 203.0.113.7'))->toBe('203.0.113.7');
    });

    it('applies limits per real client behind Cloudflare', function (): void {
        config(['qbazaar.auth.rate_limits.attempts_per_minute_per_ip' => 1]);
        withServerVariables(['REMOTE_ADDR' => '173.245.48.10']);

        postJson('/api/v1/auth/login', ['identifier' => 'a@example.qa', 'password' => 'wrong'], ['X-Forwarded-For' => '203.0.113.7'])->assertStatus(401);
        postJson('/api/v1/auth/login', ['identifier' => 'b@example.qa', 'password' => 'wrong'], ['X-Forwarded-For' => '203.0.113.8'])->assertStatus(401);
    });
});
