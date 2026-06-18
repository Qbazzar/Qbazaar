<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAds;

/**
 * Regression: Ad::toSearchableArray() must not throw "property slug on null"
 * when an ad's category/location relation is missing (orphaned FK). That crash
 * 500-ed the entire /search endpoint and blocked scout:import.
 */
uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
});

it('degrades taxonomy slugs to null instead of throwing', function (): void {
    $ad = $this->makeAd(User::factory()->create());

    // Force the relations to resolve to null, mimicking an orphaned FK.
    $ad->setRelation('category', null);
    $ad->setRelation('location', null);

    $array = $ad->toSearchableArray();

    expect($array['category_slug'])->toBeNull();
    expect($array['location_slug'])->toBeNull();
});

it('includes the taxonomy slugs when the relations are present', function (): void {
    $ad = $this->makeAd(User::factory()->create())->fresh(['category', 'location']);

    $array = $ad->toSearchableArray();

    expect($array['category_slug'])->not->toBeNull();
    expect($array['location_slug'])->not->toBeNull();
});
