<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'password' => Hash::make('Str0ng!Pass1'),
    ]);
    Sanctum::actingAs($this->user, ['*']);
});

it('flips status to DEACTIVATED, burns refresh tokens, and revokes PATs', function (): void {
    $refresh = RefreshToken::query()->create([
        'id' => strtolower((string) Str::ulid()),
        'user_id' => $this->user->id,
        'token_hash' => Hash::make('some_old_token'),
        'expires_at' => now()->addDays(30),
    ]);

    postJson('/api/v1/account/deactivate', [
        'password' => 'Str0ng!Pass1',
        'reason' => 'Taking a break',
    ])->assertNoContent();

    $fresh = $this->user->fresh();
    expect($fresh)->not->toBeNull();
    expect($fresh->status)->toBe(UserStatus::DEACTIVATED);

    expect($refresh->fresh()->used_at)->not->toBeNull();
    expect($this->user->tokens()->count())->toBe(0);
});

it('rejects when the password is wrong with USER_005', function (): void {
    postJson('/api/v1/account/deactivate', [
        'password' => 'WrongPass!1',
    ])
        ->assertStatus(422)
        ->assertJson(
            fn ($json) => $json
                ->where('error.code', 'USER_005')
                ->etc(),
        );

    expect($this->user->fresh()->status)->toBe(UserStatus::ACTIVE);
});

it('rejects unauthenticated requests', function (): void {
    $this->refreshApplication();

    postJson('/api/v1/account/deactivate', [
        'password' => 'Str0ng!Pass1',
    ])->assertStatus(401);
});
