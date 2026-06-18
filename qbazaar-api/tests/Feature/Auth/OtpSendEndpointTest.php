<?php

declare(strict_types=1);

use App\Models\OtpCode;
use App\Models\User;
use App\Notifications\Channels\TwilioSmsChannel;
use App\Notifications\OtpNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
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

it('returns 202 with expiry metadata for a valid Qatari phone', function (): void {
    $response = postJson('/api/v1/auth/send-otp', [
        'phone' => '+97455123456',
    ]);

    $response
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
});

it('routes the notification to the matching user when one exists', function (): void {
    $user = User::factory()->create(['phone' => '+97455123456']);

    postJson('/api/v1/auth/send-otp', ['phone' => '+97455123456'])->assertStatus(202);

    Notification::assertSentTo($user, OtpNotification::class);
});

it('routes the notification to the phone when no user owns it', function (): void {
    postJson('/api/v1/auth/send-otp', ['phone' => '+97455999000'])->assertStatus(202);

    Notification::assertSentOnDemand(OtpNotification::class, function ($notification, array $channels, $notifiable) {
        return in_array(TwilioSmsChannel::class, $channels, true)
            && $notifiable->routes[TwilioSmsChannel::class] === '+97455999000';
    });
});

it('queues the OTP notification so a mail/SMS delivery failure never 500s send-otp', function (): void {
    // Regression: OtpNotification used to send synchronously; a bouncing OTP
    // email (e.g. an undeliverable address) bubbled a 550 into a 500 response.
    expect(new OtpNotification('+97455123456', '123456', 300))
        ->toBeInstanceOf(ShouldQueue::class);
});

it('rejects non-Qatari phone numbers', function (): void {
    postJson('/api/v1/auth/send-otp', ['phone' => '0501234567'])
        ->assertStatus(422)
        ->assertJson(
            fn ($json) => $json
                ->where('success', false)
                ->where('error.code', 'VALIDATION_FAILED')
                ->has('error.details.phone')
                ->etc(),
        );

    Notification::assertNothingSent();
});

it('rejects a second send inside the 60-second cooldown window', function (): void {
    postJson('/api/v1/auth/send-otp', ['phone' => '+97455123456'])->assertStatus(202);

    postJson('/api/v1/auth/send-otp', ['phone' => '+97455123456'])
        ->assertStatus(429)
        ->assertJson(
            fn ($json) => $json
                ->where('error.code', 'AUTH_006')
                ->etc(),
        );
});

it('rejects after the per-phone hourly ceiling is reached', function (): void {
    $phone = '+97455123456';

    // Backfill 5 fresh OTPs (within the hour) — this matches max_per_hour.
    for ($i = 0; $i < 5; $i++) {
        OtpCode::query()->create([
            'phone' => $phone,
            'code_hash' => bcrypt('123456'),
            'attempts' => 0,
            'expires_at' => Carbon::now()->addMinutes(5),
            'used_at' => Carbon::now(),
        ]);
    }

    Cache::flush(); // ensure cooldown isn't what blocks us

    postJson('/api/v1/auth/send-otp', ['phone' => $phone])
        ->assertStatus(429)
        ->assertJson(
            fn ($json) => $json
                ->where('error.code', 'AUTH_006')
                ->etc(),
        );
});
