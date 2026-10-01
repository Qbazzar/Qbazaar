<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->ad = Ad::factory()->active()->create(['user_id' => User::factory()->create()->id, 'price' => 100]);
    Activity::query()->delete();
});

it('writes one row for a save that changes several fields', function (): void {
    $this->ad->update(['title' => 'Updated title for the ad', 'price' => 80, 'description' => str_repeat('New text. ', 5)]);

    $row = Activity::query()->where('log_name', 'ad')->sole();

    expect($row->event)->toBe('ad_updated')
        ->and($row->properties['fields'])->toBe(['title', 'price', 'description'])
        ->and($row->properties['old']['price'])->toBe('100.00')
        ->and($row->properties['new']['price'])->toBe('80.00')
        ->and($row->properties['new'])->not->toHaveKey('description');
});

it('names a status change so lifecycle transitions stay searchable', function (): void {
    $this->ad->forceFill(['status' => AdStatus::PENDING, 'title' => 'Edited live title'])->save();

    $row = Activity::query()->where('log_name', 'ad')->sole();

    expect($row->event)->toBe('status_changed')
        ->and($row->properties['old']['status'])->toBe(AdStatus::ACTIVE->value)
        ->and($row->properties['new']['status'])->toBe(AdStatus::PENDING->value);
});

it('writes nothing for counter-only saves', function (): void {
    $this->ad->forceFill(['views_count' => 10])->save();

    expect(Activity::query()->count())->toBe(0);
});
