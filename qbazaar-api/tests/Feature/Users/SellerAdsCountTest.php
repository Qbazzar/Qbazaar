<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\getJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->create();
});

function storedActiveAdsCount(User $seller): int
{
    return (int) $seller->fresh()?->active_ads_count;
}

it('counts ads in and out of the active status', function (): void {
    $ad = Ad::factory()->active()->create(['user_id' => $this->seller->id]);
    Ad::factory()->draft()->create(['user_id' => $this->seller->id]);
    expect(storedActiveAdsCount($this->seller))->toBe(1);

    $ad->forceFill(['status' => AdStatus::SOLD])->save();
    expect(storedActiveAdsCount($this->seller))->toBe(0);

    $ad->forceFill(['status' => AdStatus::ACTIVE])->save();
    expect(storedActiveAdsCount($this->seller))->toBe(1);
});

it('drops a deleted ad once, including when it is later force-deleted', function (): void {
    $ad = Ad::factory()->active()->create(['user_id' => $this->seller->id]);
    Ad::factory()->active()->create(['user_id' => $this->seller->id]);

    $ad->delete();
    expect(storedActiveAdsCount($this->seller))->toBe(1);

    $ad->restore();
    expect(storedActiveAdsCount($this->seller))->toBe(2);

    $ad->delete();
    $ad->forceDelete();
    expect(storedActiveAdsCount($this->seller))->toBe(1);
});

it('serves the public profile count without counting the ads table', function (): void {
    Ad::factory()->count(2)->active()->create(['user_id' => $this->seller->id]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    getJson("/api/v1/users/{$this->seller->id}/public-profile")
        ->assertOk()
        ->assertJsonPath('data.ads_count', 2);
    DB::disableQueryLog();

    $adCounts = array_filter(DB::getQueryLog(), fn (array $query): bool => str_contains($query['query'], 'from "ads"'));

    expect($adCounts)->toBe([]);
});
