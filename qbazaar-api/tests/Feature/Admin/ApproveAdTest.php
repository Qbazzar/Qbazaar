<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Events\Ads\AdApproved;
use App\Models\Ad;
use Database\Seeders\CategorySeeder;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Factories pull existing category / location rows; seed those before
    // invoking the factory.
    $this->seed([CategorySeeder::class, LocationSeeder::class]);
});

it('flips a pending ad to active and fires AdApproved', function (): void {
    Event::fake([AdApproved::class]);

    $ad = Ad::factory()->create([
        'status' => AdStatus::PENDING->value,
        'published_at' => null,
        'expires_at' => null,
    ]);

    // Exercise the same lifecycle path the Filament approve action uses.
    $ad->publish();
    AdApproved::dispatch($ad);

    expect($ad->refresh()->status)->toBe(AdStatus::ACTIVE);
    expect($ad->published_at)->not->toBeNull();
    expect($ad->expires_at)->not->toBeNull();

    Event::assertDispatched(AdApproved::class, fn (AdApproved $e): bool => $e->ad->id === $ad->id);
});
