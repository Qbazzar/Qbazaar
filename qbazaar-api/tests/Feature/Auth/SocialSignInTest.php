<?php

declare(strict_types=1);

use App\Enums\SocialProvider;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Auth\RefreshTokenService;
use App\Services\Auth\Social\SocialIdentity;
use App\Services\Auth\Social\SocialTokenVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\PersonalAccessToken;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

final class FakeSocialTokenVerifier implements SocialTokenVerifier
{
    /** @var array<string, SocialIdentity> */
    public array $identities = [];

    public function verify(SocialProvider $provider, string $idToken): SocialIdentity
    {
        return $this->identities[$provider->value . ':' . $idToken]
            ?? throw new DomainException(ErrorCode::AUTH_SOCIAL_TOKEN_INVALID);
    }
}

beforeEach(function (): void {
    Cache::flush();
    Notification::fake();

    $this->verifier = new FakeSocialTokenVerifier;
    $this->verifier->identities['google:good-token'] = new SocialIdentity(SocialProvider::GOOGLE, 'google-sub-1', 'owner@example.qa', 'Ahmed Al-Ali');
    $this->verifier->identities['apple:good-token'] = new SocialIdentity(SocialProvider::APPLE, 'apple-sub-1', 'relay@privaterelay.appleid.com', null);
    app()->instance(SocialTokenVerifier::class, $this->verifier);
});

it('links an existing account by its verified email and signs in', function (): void {
    $user = User::factory()->create(['email' => 'owner@example.qa', 'email_verified' => false]);

    postJson('/api/v1/auth/social/google', ['id_token' => 'good-token'])
        ->assertOk()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonStructure(['data' => ['tokens' => ['access_token', 'refresh_token']]]);

    $account = SocialAccount::query()->sole();

    expect($account->user_id)->toBe($user->id)
        ->and($account->provider)->toBe(SocialProvider::GOOGLE)
        ->and($account->provider_user_id)->toBe('google-sub-1')
        ->and($user->fresh()->email_verified)->toBeTrue();
});

it('drops the password and sessions of whoever pre-registered an unverified email', function (): void {
    $user = User::factory()->create(['email' => 'owner@example.qa', 'email_verified' => false]);
    $squatterSession = app(RefreshTokenService::class)->issue($user);

    postJson('/api/v1/auth/social/google', ['id_token' => 'good-token'])->assertOk();

    expect($user->fresh()->password)->toBeNull()
        ->and(PersonalAccessToken::findToken($squatterSession->accessToken))->toBeNull();
});

it('does not touch an account whose email differs from the provider email', function (): void {
    $user = User::factory()->create(['email' => 'renamed@example.qa', 'email_verified' => false]);
    SocialAccount::query()->create([
        'user_id' => $user->id,
        'provider' => SocialProvider::GOOGLE,
        'provider_user_id' => 'google-sub-1',
        'email' => 'owner@example.qa',
    ]);

    postJson('/api/v1/auth/social/google', ['id_token' => 'good-token'])->assertOk();

    expect($user->fresh()->email_verified)->toBeFalse()
        ->and($user->fresh()->password)->not->toBeNull();
});

it('finds a linked account by provider subject even after the email changed', function (): void {
    $user = User::factory()->create(['email' => 'renamed@example.qa']);
    SocialAccount::query()->create([
        'user_id' => $user->id,
        'provider' => SocialProvider::GOOGLE,
        'provider_user_id' => 'google-sub-1',
        'email' => 'owner@example.qa',
    ]);

    postJson('/api/v1/auth/social/google', ['id_token' => 'good-token'])
        ->assertOk()
        ->assertJsonPath('data.user.id', $user->id);

    expect(SocialAccount::query()->count())->toBe(1);
});

it('asks a new person for sign-up details and echoes what the provider shared', function (): void {
    postJson('/api/v1/auth/social/google', ['id_token' => 'good-token'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'AUTH_011')
        ->assertJsonPath('error.details.email', 'owner@example.qa')
        ->assertJsonPath('error.details.full_name', 'Ahmed Al-Ali');

    expect(User::query()->count())->toBe(0);
});

it('creates a passwordless account with an unverified phone', function (): void {
    postJson('/api/v1/auth/social/apple', [
        'id_token' => 'good-token',
        'full_name' => 'Sara Al-Kuwari',
        'phone' => '+97455777888',
        'account_type' => 'private',
        'accepted_terms' => true,
    ])
        ->assertCreated()
        ->assertJsonPath('data.user.email', 'relay@privaterelay.appleid.com')
        ->assertJsonPath('data.user.email_verified', true)
        ->assertJsonPath('data.user.phone_verified', false);

    $user = User::query()->sole();

    expect($user->password)->toBeNull()
        ->and(SocialAccount::query()->sole()->user_id)->toBe($user->id);
});

it('rejects a token the verifier refuses', function (): void {
    postJson('/api/v1/auth/social/google', ['id_token' => 'forged'])
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'AUTH_013');
});

it('answers 404 for an unsupported provider', function (): void {
    postJson('/api/v1/auth/social/facebook', ['id_token' => 'good-token'])->assertNotFound();
});

it('requires the id_token', function (): void {
    postJson('/api/v1/auth/social/google', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['id_token'], 'error.details');
});
