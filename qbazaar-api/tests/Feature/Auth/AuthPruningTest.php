<?php

declare(strict_types=1);

use App\Models\OtpCode;
use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

function otpRowExpiring(Carbon $expiresAt): OtpCode
{
    return OtpCode::query()->create([
        'recipient' => 'owner@example.qa',
        'purpose' => 'email_sign_in',
        'code_hash' => 'hash',
        'attempts' => 0,
        'expires_at' => $expiresAt,
    ]);
}

function trustedDeviceLastUsed(User $user, Carbon $lastUsedAt): TrustedDevice
{
    return TrustedDevice::query()->create([
        'user_id' => $user->id,
        'device_hash' => hash('sha256', (string) $lastUsedAt),
        'last_used_at' => $lastUsedAt,
    ]);
}

it('prunes codes that expired over a day ago and devices idle past the trust window', function (): void {
    $user = User::factory()->create();
    otpRowExpiring(Carbon::now()->subDays(2));
    $recentCode = otpRowExpiring(Carbon::now()->subHour());
    trustedDeviceLastUsed($user, Carbon::now()->subDays(181));
    $activeDevice = trustedDeviceLastUsed($user, Carbon::now()->subDays(10));

    artisan('model:prune', ['--model' => [OtpCode::class, TrustedDevice::class]])->assertSuccessful();

    expect(OtpCode::query()->pluck('id')->all())->toBe([$recentCode->id])
        ->and(TrustedDevice::query()->pluck('id')->all())->toBe([$activeDevice->id]);
});

it('schedules the daily prune', function (): void {
    artisan('schedule:list')->expectsOutputToContain('model:prune');
});
