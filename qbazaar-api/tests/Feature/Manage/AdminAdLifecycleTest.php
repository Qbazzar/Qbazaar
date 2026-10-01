<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Events\Ads\AdApproved;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\actingAs;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->withoutVite();
    config(['scout.driver' => null]);

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seedReferenceData();

    actingAs(User::factory()->create()->assignRole('super_admin'));
    $this->seller = User::factory()->create();
});

it('approves a pending ad and starts its lifetime', function (): void {
    Event::fake([AdApproved::class]);
    $ad = $this->makeAd($this->seller, ['status' => AdStatus::PENDING->value]);

    $this->post("/admin/ads/{$ad->id}/approve")->assertRedirect()->assertSessionHasNoErrors();

    $fresh = $ad->fresh();
    expect($fresh->status)->toBe(AdStatus::ACTIVE)
        ->and($fresh->published_at)->not->toBeNull()
        ->and($fresh->expires_at?->isFuture())->toBeTrue();
    Event::assertDispatched(AdApproved::class);
});

it('refuses admin actions that do not apply to the current status', function (string $action, AdStatus $status): void {
    Event::fake([AdApproved::class]);
    $ad = $this->makeAd($this->seller, ['status' => $status->value]);

    $this->from("/admin/ads/{$ad->id}")
        ->post("/admin/ads/{$ad->id}/{$action}", ['admin_notes' => 'Not allowed'])
        ->assertRedirect("/admin/ads/{$ad->id}")
        ->assertSessionHas('error');

    expect($ad->fresh()->status)->toBe($status);
    Event::assertNotDispatched(AdApproved::class);
})->with([
    'approve a sold ad' => ['approve', AdStatus::SOLD],
    'approve a draft' => ['approve', AdStatus::DRAFT],
    'reject an active ad' => ['reject', AdStatus::ACTIVE],
    'suspend a draft' => ['suspend', AdStatus::DRAFT],
    'unsuspend an active ad' => ['unsuspend', AdStatus::ACTIVE],
    'unsuspend an expired ad' => ['unsuspend', AdStatus::EXPIRED],
    'expire a pending ad' => ['force-expire', AdStatus::PENDING],
]);

it('suspends and restores a live ad', function (): void {
    $ad = $this->makeAd($this->seller, ['status' => AdStatus::ACTIVE->value, 'published_at' => now()]);

    $this->post("/admin/ads/{$ad->id}/suspend")->assertRedirect();
    expect($ad->fresh()->status)->toBe(AdStatus::BLOCKED);

    $this->post("/admin/ads/{$ad->id}/unsuspend")->assertRedirect();
    expect($ad->fresh()->status)->toBe(AdStatus::ACTIVE);
});

it('force-expires a live ad', function (): void {
    $ad = $this->makeAd($this->seller, ['status' => AdStatus::ACTIVE->value, 'published_at' => now()]);

    $this->post("/admin/ads/{$ad->id}/force-expire")->assertRedirect()->assertSessionMissing('error');

    expect($ad->fresh()->status)->toBe(AdStatus::EXPIRED);
});

it('ignores a status sent through the edit form', function (): void {
    $ad = $this->makeAd($this->seller, ['status' => AdStatus::DRAFT->value]);

    $this->put("/admin/ads/{$ad->id}", [
        'title' => 'Edited title from the panel',
        'description' => $ad->description,
        'category_id' => $ad->category_id,
        'location_id' => $ad->location_id,
        'price' => 100,
        'price_type' => 'fixed',
        'status' => AdStatus::ACTIVE->value,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $fresh = $ad->fresh();
    expect($fresh->title)->toBe('Edited title from the panel')
        ->and($fresh->status)->toBe(AdStatus::DRAFT);
});
