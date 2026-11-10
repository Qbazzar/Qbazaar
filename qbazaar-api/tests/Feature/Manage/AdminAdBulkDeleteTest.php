<?php

declare(strict_types=1);

use App\Data\Moderation\SanctionReasonInput;
use App\Enums\SanctionReason;
use App\Exceptions\DomainException;
use App\Models\Ad;
use App\Models\ModerationSanction;
use App\Models\User;
use App\Services\Moderation\SanctionService;
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

    $this->post('/admin/ads/bulk-destroy', ['ids' => array_map(fn (Ad $ad): string => $ad->id, $selected), 'reason' => 'spam'])
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

    $this->post('/admin/ads/bulk-destroy', ['ids' => [$ad->id, '1 OR 1=1'], 'reason' => 'spam'])
        ->assertSessionHasErrors('ids.1');

    expect(Ad::query()->whereKey($ad->id)->exists())->toBeTrue();
});

it('caps how many ads one request can delete', function (): void {
    config(['qbazaar.admin.bulk_action_max' => 1]);
    $owner = User::factory()->create();

    $this->post('/admin/ads/bulk-destroy', ['ids' => [$this->makeAd($owner)->id, $this->makeAd($owner)->id], 'reason' => 'spam'])
        ->assertSessionHasErrors('ids');

    expect(Ad::query()->count())->toBe(2);
});

it('skips the selected ads another staff member already removed, without sanctioning them again', function (): void {
    app()->setLocale('en');
    $owner = User::factory()->create();
    $first = $this->makeAd($owner);
    $second = $this->makeAd($owner);

    $this->post('/admin/ads/bulk-destroy', ['ids' => [$first->id], 'reason' => 'spam'])->assertSessionHasNoErrors();

    $this->post('/admin/ads/bulk-destroy', ['ids' => [$first->id, $second->id], 'reason' => 'spam'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'Selected ads removed: 1. 1 were left alone because they were already removed.');

    expect(ModerationSanction::query()->where('subject_id', $first->id)->count())->toBe(1)
        ->and(ModerationSanction::query()->where('subject_id', $second->id)->count())->toBe(1)
        ->and(Activity::query()->where('event', 'ad_deleted')->count())->toBe(2);
});

it('refuses to remove one ad twice when two staff act on the same page', function (): void {
    $staff = User::factory()->create()->assignRole('moderator');
    $ad = $this->makeAd(User::factory()->create());
    $staleCopy = Ad::query()->findOrFail($ad->id);
    $sanctions = app(SanctionService::class);

    $sanctions->removeAd($staff, $ad, new SanctionReasonInput(SanctionReason::SPAM));

    expect(fn () => $sanctions->removeAd($staff, $staleCopy, new SanctionReasonInput(SanctionReason::FRAUD)))
        ->toThrow(DomainException::class, __('admin.moderation.errors.ad_already_removed'));

    expect(ModerationSanction::query()->where('subject_id', $ad->id)->sole()->reason)->toBe(SanctionReason::SPAM);
});
