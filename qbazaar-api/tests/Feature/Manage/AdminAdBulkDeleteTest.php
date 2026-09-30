<?php

declare(strict_types=1);

use App\Models\Ad;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->withoutVite();
    config(['scout.driver' => null]);

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seedReferenceData();

    actingAs(User::factory()->create()->assignRole('super_admin'));
});

it('deletes the selected ads through the model', function (): void {
    $owner = User::factory()->create();
    $selected = [$this->makeAd($owner), $this->makeAd($owner)];
    $untouched = $this->makeAd($owner);

    $this->post('/admin/ads/bulk-destroy', ['ids' => array_map(fn (Ad $ad): string => $ad->id, $selected)])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    foreach ($selected as $ad) {
        expect(Ad::query()->whereKey($ad->id)->exists())->toBeFalse()
            ->and(Ad::withTrashed()->find($ad->id)?->trashed())->toBeTrue();
    }

    expect(Ad::query()->whereKey($untouched->id)->exists())->toBeTrue()
        ->and(Activity::query()->where('event', 'ad_deleted')->count())->toBe(2);
});

it('rejects ids that are not ULIDs', function (): void {
    $ad = $this->makeAd(User::factory()->create());

    $this->post('/admin/ads/bulk-destroy', ['ids' => [$ad->id, '1 OR 1=1']])
        ->assertSessionHasErrors('ids.1');

    expect(Ad::query()->whereKey($ad->id)->exists())->toBeTrue();
});

it('caps how many ads one request can delete', function (): void {
    config(['qbazaar.admin.bulk_action_max' => 1]);
    $owner = User::factory()->create();

    $this->post('/admin/ads/bulk-destroy', ['ids' => [$this->makeAd($owner)->id, $this->makeAd($owner)->id]])
        ->assertSessionHasErrors('ids');

    expect(Ad::query()->count())->toBe(2);
});
