<?php

declare(strict_types=1);

use App\Services\Catalog\WarmedCache;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::flush();
    $this->cache = new WarmedCache;
    $this->builds = 0;
    $this->build = function (): array {
        $this->builds++;

        return ['built' => $this->builds];
    };
});

it('builds a missing entry once and serves it from the cache afterwards', function (): void {
    expect($this->cache->get('catalog.test', 60, $this->build))->toBe(['built' => 1])
        ->and($this->cache->get('catalog.test', 60, $this->build))->toBe(['built' => 1])
        ->and($this->builds)->toBe(1);
});

it('never rebuilds on read while the warmer keeps the entry', function (): void {
    $this->cache->put('catalog.test', 60, $this->build);
    $this->cache->get('catalog.test', 60, $this->build);
    $this->cache->put('catalog.test', 60, $this->build);

    expect($this->cache->get('catalog.test', 60, $this->build))->toBe(['built' => 2])
        ->and($this->builds)->toBe(2);
});

it('releases the rebuild lock once the entry is built', function (): void {
    $this->cache->get('catalog.test', 60, $this->build);

    expect(Cache::lock('catalog.test:build', 1)->get())->toBeTrue();
});
