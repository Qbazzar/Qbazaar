<?php

declare(strict_types=1);

use App\Models\OtpCode;
use App\Notifications\OtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    RateLimiter::clear('otp|+97455123456');
    RateLimiter::clear('otp|127.0.0.1');
    Notification::fake();
});

it('returns 202 + sends a notification on first resend', function (): void {
    postJson('/api/v1/auth/resend-otp', ['phone' => '+97455123456'])
        ->assertStatus(202)
        ->assertJson(
            fn ($json) => $json
                ->where('success', true)
                ->where('data.sent_to', '+97455123456')
                ->where('data.expires_in', 300)
                ->where('data.can_resend_in', 60)
                ->etc(),
        );

    expect(OtpCode::query()->where('phone', '+97455123456')->count())->toBe(1);

    Notification::assertSentOnDemand(OtpNotification::class);
});

it('refuses a second resend inside the 60-second cooldown window', function (): void {
    postJson('/api/v1/auth/resend-otp', ['phone' => '+97455123456'])->assertStatus(202);

    postJson('/api/v1/auth/resend-otp', ['phone' => '+97455123456'])
        ->assertStatus(429)
        ->assertJson(
            fn ($json) => $json
                ->where('success', false)
                ->where('error.code', 'AUTH_006')
                ->etc(),
        );
});

it('refuses resend after the per-phone hourly ceiling is reached', function (): void {
    $phone = '+97455123456';

    // Backfill 5 fresh OTPs within the rolling hour — equals the configured ceiling.
    for ($i = 0; $i < 5; $i++) {
        OtpCode::query()->create([
            'phone' => $phone,
            'code_hash' => bcrypt('123456'),
            'attempts' => 0,
            'expires_at' => Carbon::now()->addMinutes(5),
            'used_at' => Carbon::now(),
        ]);
    }

    Cache::flush(); // make sure it isn't the cooldown that rejects us

    postJson('/api/v1/auth/resend-otp', ['phone' => $phone])
        ->assertStatus(429)
        ->assertJson(
            fn ($json) => $json
                ->where('error.code', 'AUTH_006')
                ->etc(),
        );
});

it('replaces the previous active OTP row when called outside the cooldown', function (): void {
    $phone = '+97455123456';

    // Seed a live, previously-issued OTP for this phone.
    $original = OtpCode::query()->create([
        'phone' => $phone,
        'code_hash' => bcrypt('111111'),
        'attempts' => 0,
        'expires_at' => Carbon::now()->addMinutes(5),
        'used_at' => null,
    ]);

    // Cooldown lives in cache; flushing simulates "60s have passed".
    Cache::flush();

    postJson('/api/v1/auth/resend-otp', ['phone' => $phone])->assertStatus(202);

    // Old row is now soft-burnt (used_at populated) and a new active row exists.
    expect($original->fresh()->used_at)->not->toBeNull();

    $active = OtpCode::query()
        ->where('phone', $phone)
        ->whereNull('used_at')
        ->get();

    expect($active)->toHaveCount(1)
        ->and($active->first()->id)->not->toBe($original->id);
});
