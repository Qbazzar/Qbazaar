<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Enums\UserStatus;
use App\Models\Ad;
use App\Models\User;
use App\Services\Auth\OtpService;
use Database\Seeders\CategorySeeder;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\options;
use function Pest\Laravel\seed;

use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

describe('super admin bypass', function (): void {
    it('does not let a super admin override ownership rules on the API', function (): void {
        seed([CategorySeeder::class, LocationSeeder::class]);
        Role::findOrCreate('super_admin', 'web');
        $admin = User::factory()->phoneVerified()->create();
        $admin->assignRole('super_admin');
        $ad = Ad::factory()->create(['status' => AdStatus::ACTIVE->value]);

        Sanctum::actingAs($admin, ['*']);

        deleteJson("/api/v1/ads/{$ad->id}")->assertForbidden();
        expect(Ad::query()->whereKey($ad->id)->exists())->toBeTrue();
    });
});

it('ignores sensitive user fields on mass assignment', function (): void {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE, 'phone_verified' => false]);

    $user->fill([
        'full_name' => 'Renamed',
        'status' => UserStatus::SUSPENDED->value,
        'phone_verified' => true,
        'email_verified' => true,
        'deletion_requested_at' => now(),
    ])->save();

    $user->refresh();

    expect($user->full_name)->toBe('Renamed')
        ->and($user->status)->toBe(UserStatus::ACTIVE)
        ->and($user->phone_verified)->toBeFalse()
        ->and($user->deletion_requested_at)->toBeNull();
});

describe('OTP_FIXED_CODE', function (): void {
    beforeEach(function (): void {
        config(['qbazaar.otp.fixed_code' => '123456']);
    });

    it('is honoured outside production', function (): void {
        expect(app(OtpService::class)->issue('+97455123456')->rawCode)->toBe('123456');
    });

    it('is ignored in production', function (): void {
        app()->detectEnvironment(fn (): string => 'production');
        $log = Log::spy();

        app(OtpService::class)->issue('+97455123456');

        $log->shouldHaveReceived('critical');
        expect(app(OtpService::class)->issue('+97455123457')->rawCode)->not->toBe('123456');
    });
});

describe('CORS', function (): void {
    beforeEach(function (): void {
        config(['cors.allowed_origins' => ['https://qbazaar.qa']]);
    });

    it('allows the configured web origin', function (): void {
        options('/api/v1/health', [], [
            'Origin' => 'https://qbazaar.qa',
            'Access-Control-Request-Method' => 'GET',
        ])->assertHeader('Access-Control-Allow-Origin', 'https://qbazaar.qa');
    });

    it('does not allow other origins', function (): void {
        $response = options('/api/v1/health', [], [
            'Origin' => 'https://evil.example',
            'Access-Control-Request-Method' => 'GET',
        ]);

        expect($response->headers->get('Access-Control-Allow-Origin'))->not->toBe('https://evil.example')
            ->and($response->headers->get('Access-Control-Allow-Origin'))->not->toBe('*');
    });
});

describe('API docs', function (): void {
    it('are hidden when disabled', function (): void {
        config(['qbazaar.api_docs_enabled' => false]);

        get('/swagger')->assertNotFound();
        get('/docs')->assertNotFound();
        getJson('/api/v1/openapi.yaml')->assertNotFound();
    });

    it('are served when enabled', function (): void {
        config(['qbazaar.api_docs_enabled' => true]);

        get('/docs')->assertOk();
        get('/api/v1/openapi.yaml')->assertOk();
    });
});
