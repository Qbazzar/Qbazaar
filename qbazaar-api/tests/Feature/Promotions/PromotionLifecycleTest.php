<?php

declare(strict_types=1);

use App\Actions\Catalog\GetHomeFeedAction;
use App\Enums\AdStatus;
use App\Enums\LedgerAccountType;
use App\Enums\PromotionPaymentMethod;
use App\Enums\PromotionStatus;
use App\Enums\PromotionType;
use App\Events\Promotions\PromotionActivated;
use App\Events\Promotions\PromotionExpired;
use App\Events\Promotions\PromotionRejected;
use App\Jobs\Promotions\ExpirePromotionsBatchJob;
use App\Jobs\Promotions\ExpirePromotionsJob;
use App\Models\AdPromotion;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Services\Ledger\LedgerAccounts;
use App\Services\Promotions\PromotionLifecycleService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\actingAs;

use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->seedReferenceData();
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
    $this->owner = User::factory()->create();
    $this->ad = $this->makeAd($this->owner, ['status' => AdStatus::ACTIVE->value]);
    $this->lifecycle = app(PromotionLifecycleService::class);
});

function pendingPromotionTransfer(object $test, PromotionType $type = PromotionType::PREMIUM): AdPromotion
{
    return $test->lifecycle->purchase($test->owner, $test->ad, $type, PromotionPaymentMethod::BANK_TRANSFER, 'TRX-77');
}

it('starts a promotion when an admin confirms its transfer and books it against the bank', function (): void {
    Event::fake([PromotionActivated::class]);
    $promotion = pendingPromotionTransfer($this);

    actingAs($this->admin)
        ->post('/admin/finance/promotions/' . $promotion->id . '/confirm')
        ->assertRedirect('/admin/finance/promotions')
        ->assertSessionHas('status', __('admin.promotion_transfers.confirmed'));

    $promotion->refresh();
    $accounts = app(LedgerAccounts::class);

    expect($promotion->status)->toBe(PromotionStatus::ACTIVE)
        ->and($promotion->reviewed_by)->toBe($this->admin->id)
        ->and($promotion->ends_at->toDateString())->toBe(now()->addDays($promotion->duration_days)->toDateString())
        ->and($this->ad->fresh()->promotion_rank)->toBe(PromotionType::PREMIUM->rank())
        ->and($accounts->balanceOf(LedgerAccountType::PLATFORM_BANK))->toBe($promotion->price)
        ->and($accounts->balanceOf(LedgerAccountType::PLATFORM_REVENUE))->toBe($promotion->price)
        ->and(Activity::query()->where('log_name', 'finance')->sole()->causer_id)->toBe($this->admin->id);

    Event::assertDispatched(PromotionActivated::class);
});

it('books a confirmed transfer once even when confirmed twice', function (): void {
    $promotion = pendingPromotionTransfer($this);

    actingAs($this->admin)->post('/admin/finance/promotions/' . $promotion->id . '/confirm');
    actingAs($this->admin)
        ->from('/admin/finance/promotions')
        ->post('/admin/finance/promotions/' . $promotion->id . '/confirm')
        ->assertRedirect('/admin/finance/promotions')
        ->assertSessionHas('error');

    expect(LedgerTransaction::query()->count())->toBe(1);
});

it('rejects a transfer without booking anything and frees the slot', function (): void {
    Event::fake([PromotionRejected::class]);
    $promotion = pendingPromotionTransfer($this);

    actingAs($this->admin)
        ->post('/admin/finance/promotions/' . $promotion->id . '/reject', ['reason' => 'No money arrived'])
        ->assertRedirect('/admin/finance/promotions');

    $promotion->refresh();

    expect($promotion->status)->toBe(PromotionStatus::REJECTED)
        ->and($promotion->rejection_reason)->toBe('No money arrived')
        ->and($promotion->open_slot)->toBeNull()
        ->and(LedgerTransaction::query()->count())->toBe(0);

    Event::assertDispatched(PromotionRejected::class);
    expect(pendingPromotionTransfer($this)->status)->toBe(PromotionStatus::PENDING_PAYMENT);
});

it('lists the transfers waiting for confirmation to finance staff', function (): void {
    $promotion = pendingPromotionTransfer($this);

    actingAs($this->admin)->get('/admin/finance/promotions')
        ->assertOk()
        ->assertSee($promotion->transfer_reference)
        ->assertSee($this->ad->title);
});

it('keeps the transfer actions behind finance.manage', function (): void {
    $promotion = pendingPromotionTransfer($this);
    $viewer = User::factory()->create();
    $viewer->assignRole('moderator');
    $viewer->givePermissionTo('finance.view');

    actingAs($viewer)->get('/admin/finance/promotions')->assertOk();
    actingAs($viewer)->post('/admin/finance/promotions/' . $promotion->id . '/confirm')->assertForbidden();
    actingAs($viewer)->post('/admin/finance/promotions/' . $promotion->id . '/reject')->assertForbidden();

    $moderator = User::factory()->create();
    $moderator->assignRole('moderator');
    actingAs($moderator)->get('/admin/finance/promotions')->assertForbidden();

    expect($promotion->fresh()->status)->toBe(PromotionStatus::PENDING_PAYMENT);
});

it('expires due promotions and drops the ad back to its next promotion', function (): void {
    Event::fake([PromotionExpired::class]);
    $premium = AdPromotion::factory()->create([
        'ad_id' => $this->ad->id, 'user_id' => $this->owner->id, 'type' => 'premium',
        'status' => 'active', 'starts_at' => now()->subDays(14), 'ends_at' => now()->subMinute(),
    ]);
    AdPromotion::factory()->create([
        'ad_id' => $this->ad->id, 'user_id' => $this->owner->id, 'type' => 'highlight',
        'status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addDay(),
    ]);
    $this->ad->forceFill(['promotion_rank' => 4])->save();

    (new ExpirePromotionsJob)->handle();

    expect($premium->fresh()->status)->toBe(PromotionStatus::EXPIRED)
        ->and($premium->fresh()->expired_at)->not->toBeNull()
        ->and($this->ad->fresh()->promotion_rank)->toBe(1);

    Event::assertDispatchedTimes(PromotionExpired::class, 1);
});

it('queues the expiry in batches and leaves running promotions alone', function (): void {
    Bus::fake([ExpirePromotionsBatchJob::class]);
    $due = AdPromotion::factory()->create(['status' => 'active', 'ends_at' => now()->subMinute()]);
    AdPromotion::factory()->create(['status' => 'active', 'ends_at' => now()->addDay()]);
    AdPromotion::factory()->create(['ends_at' => now()->subMinute()]);

    (new ExpirePromotionsJob)->handle();

    Bus::assertDispatched(ExpirePromotionsBatchJob::class, fn (ExpirePromotionsBatchJob $job): bool => $job->promotionIds === [$due->id]);
});

it('changes nothing when a promotion is expired twice', function (): void {
    $promotion = AdPromotion::factory()->create(['ad_id' => $this->ad->id, 'status' => 'active', 'ends_at' => now()->subMinute()]);

    $this->lifecycle->expireIfDue($promotion);
    $expiredAt = $promotion->fresh()->expired_at;
    $this->lifecycle->expireIfDue($promotion);

    expect($promotion->fresh()->expired_at->equalTo($expiredAt))->toBeTrue();
});

it('puts promoted ads first in the home feed', function (): void {
    $popular = $this->makeAd(User::factory()->create(), ['status' => AdStatus::ACTIVE->value, 'views_count' => 500, 'published_at' => now()]);
    $this->ad->forceFill(['promotion_rank' => 2, 'views_count' => 0, 'published_at' => now()->subDays(60)])->save();

    $ids = app(GetHomeFeedAction::class)->execute()->recommended->modelKeys();

    expect($ids[0])->toBe($this->ad->id)
        ->and($ids)->toContain($popular->id)
        ->and(array_count_values($ids)[$this->ad->id])->toBe(1);
});

it('sends the promotion rank to the search index', function (): void {
    $this->ad->forceFill(['promotion_rank' => 3])->save();

    expect($this->ad->fresh()->toSearchableArray()['promotion_rank'])->toBe(3);
});
