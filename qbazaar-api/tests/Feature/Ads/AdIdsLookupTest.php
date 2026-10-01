<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\Ad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\getJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->create();
});

it('returns the requested ads in the order they were given', function (): void {
    [$first, $second, $third] = Ad::factory()->count(3)->active()->create(['user_id' => $this->seller->id])->all();

    $ids = getJson("/api/v1/ads?ids={$third->id},{$first->id},{$second->id}")
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$third->id, $first->id, $second->id]);
});

it('accepts the array form and drops duplicates', function (): void {
    [$first, $second] = Ad::factory()->count(2)->active()->create(['user_id' => $this->seller->id])->all();

    $ids = getJson("/api/v1/ads?ids[]={$second->id}&ids[]={$first->id}&ids[]={$second->id}")
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$second->id, $first->id]);
});

it('matches ids sent in upper case', function (): void {
    [$first, $second] = Ad::factory()->count(2)->active()->create(['user_id' => $this->seller->id])->all();

    $ids = getJson('/api/v1/ads?ids=' . strtoupper("{$second->id},{$first->id}"))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$second->id, $first->id]);
});

it('skips ads that are not publicly listed or do not exist', function (): void {
    $live = Ad::factory()->active()->create(['user_id' => $this->seller->id]);
    $sold = Ad::factory()->sold()->create(['user_id' => $this->seller->id]);
    $draft = Ad::factory()->draft()->create(['user_id' => $this->seller->id]);
    $suspendedSeller = User::factory()->create(['status' => UserStatus::SUSPENDED->value]);
    $hidden = Ad::factory()->active()->create(['user_id' => $suspendedSeller->id]);
    $missing = (string) Str::ulid();

    $response = getJson("/api/v1/ads?ids={$sold->id},{$live->id},{$draft->id},{$hidden->id},{$missing}")
        ->assertOk();

    expect($response->json('data.*.id'))->toBe([$live->id])
        ->and($response->json('meta.total'))->toBe(1);
});

it('rejects more ids than the configured cap', function (): void {
    config(['qbazaar.search.ids_lookup_max' => 2]);
    $ids = implode(',', [Str::ulid(), Str::ulid(), Str::ulid()]);

    getJson("/api/v1/ads?ids={$ids}")
        ->assertStatus(422)
        ->assertJsonStructure(['error' => ['details' => ['ids']]]);
});

it('rejects ids that are not ULIDs', function (): void {
    getJson('/api/v1/ads?ids=not-an-id')->assertStatus(422);
});

it('falls back to the normal feed when ids is empty', function (): void {
    Ad::factory()->count(2)->active()->create(['user_id' => $this->seller->id]);

    expect(getJson('/api/v1/ads?ids=')->assertOk()->json('data'))->toHaveCount(2);
});
