<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;
use function Pest\Laravel\withServerVariables;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    Cache::flush();
    Notification::fake();
});

function fromIp(string $ip): void
{
    withServerVariables(['REMOTE_ADDR' => $ip]);
}

describe('login identifier lockout', function (): void {
    beforeEach(function (): void {
        config(['qbazaar.auth.max_login_attempts' => 3]);

        User::factory()->create([
            'email' => 'target@example.qa',
            'password' => Hash::make('Str0ng!Pass'),
        ]);
    });

    it('locks the identifier after repeated failures from different IPs', function (): void {
        foreach (['10.0.0.1', '10.0.0.2', '10.0.0.3'] as $ip) {
            fromIp($ip);
            postJson('/api/v1/auth/login', ['identifier' => 'target@example.qa', 'password' => 'wrong-guess'])
                ->assertStatus(401);
        }

        fromIp('10.0.0.4');
        postJson('/api/v1/auth/login', ['identifier' => 'TARGET@example.qa', 'password' => 'Str0ng!Pass'])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'AUTH_006')
            ->assertJsonPath('error.details.retry_after', fn (int $seconds): bool => $seconds > 0);
    });

    it('resets the failure count after a successful sign-in', function (): void {
        $attempt = fn (string $password) => postJson('/api/v1/auth/login', [
            'identifier' => 'target@example.qa',
            'password' => $password,
        ]);

        $attempt('wrong-guess')->assertStatus(401);
        $attempt('wrong-guess')->assertStatus(401);
        $attempt('Str0ng!Pass')->assertOk();
        $attempt('wrong-guess')->assertStatus(401);
        $attempt('Str0ng!Pass')->assertOk();
    });
});

describe('OTP limits', function (): void {
    it('caps sends per phone per day across IPs', function (): void {
        config(['qbazaar.otp.max_per_day_per_phone' => 1]);

        fromIp('10.0.0.1');
        postJson('/api/v1/auth/send-otp', ['phone' => '+97455123456'])->assertStatus(202);

        fromIp('10.0.0.2');
        postJson('/api/v1/auth/send-otp', ['phone' => '+97455123456'])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMIT_EXCEEDED');
    });

    it('caps sends per IP per day across phones', function (): void {
        config(['qbazaar.otp.max_per_day_per_ip' => 1]);

        postJson('/api/v1/auth/send-otp', ['phone' => '+97455123456'])->assertStatus(202);
        postJson('/api/v1/auth/send-otp', ['phone' => '+97455654321'])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMIT_EXCEEDED');
    });

    it('does not count verification attempts against the send caps', function (): void {
        config(['qbazaar.otp.max_per_day_per_phone' => 1, 'qbazaar.otp.max_per_day_per_ip' => 1]);

        postJson('/api/v1/auth/send-otp', ['phone' => '+97455123456'])->assertStatus(202);

        postJson('/api/v1/auth/verify-otp', ['phone' => '+97455123456', 'code' => '000000'])
            ->assertStatus(422);
    });

    it('rejects a non-string phone with a validation error', function (): void {
        postJson('/api/v1/auth/send-otp', ['phone' => ['+97455123456']])->assertStatus(422);
        postJson('/api/v1/auth/verify-otp', ['phone' => ['+97455123456'], 'code' => '000000'])->assertStatus(422);
    });

    it('limits verification attempts per IP and phone', function (): void {
        config(['qbazaar.otp.verify_max_per_minute' => 1]);

        postJson('/api/v1/auth/verify-otp', ['phone' => '+97455123456', 'code' => '000000'])->assertStatus(422);
        postJson('/api/v1/auth/verify-otp', ['phone' => '+97455123456', 'code' => '000000'])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMIT_EXCEEDED');
    });
});

describe('conversation and offer limits', function (): void {
    beforeEach(function (): void {
        $this->seedReferenceData();
        $this->seller = User::factory()->phoneVerified()->create();
        $this->buyer = User::factory()->phoneVerified()->create();
    });

    it('throttles starting conversations per user', function (): void {
        config(['qbazaar.messaging.new_conversations_per_minute' => 1]);
        $firstAd = $this->makeAd($this->seller, ['status' => AdStatus::ACTIVE->value]);
        $secondAd = $this->makeAd($this->seller, ['status' => AdStatus::ACTIVE->value]);

        Sanctum::actingAs($this->buyer, ['*']);

        postJson('/api/v1/conversations', ['ad_id' => $firstAd->id])->assertCreated();
        postJson('/api/v1/conversations', ['ad_id' => $secondAd->id])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMIT_EXCEEDED');
    });

    it('throttles making offers per user', function (): void {
        config(['qbazaar.offers.max_per_minute' => 1]);
        $ad = $this->makeAd($this->seller, ['status' => AdStatus::ACTIVE->value]);
        $conversation = Conversation::query()->create([
            'ad_id' => $ad->id,
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
        ]);

        Sanctum::actingAs($this->buyer, ['*']);

        postJson("/api/v1/conversations/{$conversation->id}/offers", ['amount' => 1500])->assertCreated();
        postJson("/api/v1/conversations/{$conversation->id}/offers", ['amount' => 1400])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMIT_EXCEEDED');
    });
});
