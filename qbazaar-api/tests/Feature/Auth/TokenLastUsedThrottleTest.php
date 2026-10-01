<?php

declare(strict_types=1);

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\getJson;
use function Pest\Laravel\withToken;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->plainToken = $this->user->createToken('api')->plainTextToken;
});

/**
 * Sends one authenticated request and returns the UPDATEs it ran against
 * personal_access_tokens.
 *
 * @return list<string>
 */
function tokenWritesDuringRequest(string $plainToken): array
{
    $writes = [];
    DB::listen(function ($query) use (&$writes): void {
        if (str_starts_with(strtolower($query->sql), 'update') && str_contains($query->sql, 'personal_access_tokens')) {
            $writes[] = $query->sql;
        }
    });

    app('auth')->forgetGuards();
    withToken($plainToken);
    getJson('/api/v1/me')->assertOk();

    return $writes;
}

it('uses the throttled token model for Sanctum', function (): void {
    expect($this->user->tokens()->first())->toBeInstanceOf(PersonalAccessToken::class);
});

it('stamps last_used_at on the first request', function (): void {
    expect(tokenWritesDuringRequest($this->plainToken))->toHaveCount(1)
        ->and($this->user->tokens()->first()?->last_used_at)->not->toBeNull();
});

it('does not rewrite a fresh stamp', function (): void {
    tokenWritesDuringRequest($this->plainToken);
    $this->travel(4)->minutes();

    expect(tokenWritesDuringRequest($this->plainToken))->toBeEmpty();
});

it('rewrites the stamp once it is older than the interval', function (): void {
    tokenWritesDuringRequest($this->plainToken);
    $this->travel(6)->minutes();

    expect(tokenWritesDuringRequest($this->plainToken))->toHaveCount(1)
        ->and($this->user->tokens()->first()?->last_used_at?->equalTo(Carbon::now()->startOfSecond()))->toBeTrue();
});

it('still saves other changes to a token', function (): void {
    tokenWritesDuringRequest($this->plainToken);

    $token = $this->user->tokens()->firstOrFail();
    $token->forceFill(['device_label' => 'Pixel 9', 'last_used_at' => Carbon::now()])->save();

    expect($token->fresh()?->device_label)->toBe('Pixel 9');
});
