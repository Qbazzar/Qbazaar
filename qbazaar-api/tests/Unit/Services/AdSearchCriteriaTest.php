<?php

declare(strict_types=1);

use App\Services\Search\AdSearchCriteria;

it('always pins the status filter', function (): void {
    expect((new AdSearchCriteria)->filter([]))->toBe('status = "active"');
});

it('adds a radius filter in metres around the given point', function (): void {
    $filter = (new AdSearchCriteria)->filter(['lat' => '25.2854', 'lng' => 51.531, 'radius_km' => 2.5]);

    expect($filter)->toBe('status = "active" AND _geoRadius(25.2854, 51.531, 2500)');
});

it('ignores a radius without a complete point', function (): void {
    expect((new AdSearchCriteria)->filter(['lat' => 25.2, 'radius_km' => 5]))->toBe('status = "active"');
});

it('combines the radius with the other filters', function (): void {
    $filter = (new AdSearchCriteria)->filter([
        'price_max' => 500,
        'lat' => 25.3,
        'lng' => 51.5,
        'radius_km' => 10,
        'custom_fields' => ['make' => 'Toy"ota'],
    ]);

    expect($filter)->toBe('status = "active" AND price <= 500 AND _geoRadius(25.3, 51.5, 10000) AND custom_fields.make = "Toy\"ota"');
});

it('sorts by distance from the given point, newest first on ties', function (): void {
    expect((new AdSearchCriteria)->sort(['sort' => 'distance', 'lat' => 25.3, 'lng' => 51.5]))
        ->toBe(['_geoPoint(25.3, 51.5):asc', 'published_at:desc']);
});

it('falls back to the newest first when distance sort has no point', function (): void {
    expect((new AdSearchCriteria)->sort(['sort' => 'distance']))->toBe(['promotion_rank:desc', 'published_at:desc']);
});

it('sorts most viewed first, promoted ads leading', function (): void {
    expect((new AdSearchCriteria)->sort(['sort' => 'most_viewed']))->toBe(['promotion_rank:desc', 'views_count:desc', 'published_at:desc']);
});

it('defaults to the newest first, promoted ads leading', function (): void {
    expect((new AdSearchCriteria)->sort([]))->toBe(['promotion_rank:desc', 'published_at:desc'])
        ->and((new AdSearchCriteria)->sort(['sort' => 'price_asc']))->toBe(['price:asc']);
});
