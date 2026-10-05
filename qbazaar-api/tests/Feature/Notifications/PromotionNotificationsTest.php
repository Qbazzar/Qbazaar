<?php

declare(strict_types=1);

use App\Data\Account\NotificationPreferences;
use App\Enums\AdStatus;
use App\Enums\PromotionNotice;
use App\Enums\PromotionPaymentMethod;
use App\Enums\PromotionStatus;
use App\Enums\PromotionType;
use App\Enums\QueueName;
use App\Events\Promotions\PromotionActivated;
use App\Events\Promotions\PromotionExpired;
use App\Events\Promotions\PromotionRejected;
use App\Exceptions\DomainException;
use App\Models\AdPromotion;
use App\Models\User;
use App\Notifications\Promotions\PromotionActivityNotification;
use App\Services\Promotions\PromotionLifecycleService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesAds;
use Tests\Concerns\ManagesMoney;

uses(RefreshDatabase::class, CreatesAds::class, ManagesMoney::class);

/**
 * What the seller hears about a promotion they bought.
 */
beforeEach(function (): void {
    Notification::fake();
    $this->seedReferenceData();
    $this->owner = User::factory()->create();
    $this->ad = $this->makeAd($this->owner, ['status' => AdStatus::ACTIVE->value, 'title' => 'Blue bike']);
});

/**
 * @return list<string>
 */
function promotionNoticesFor(User $user): array
{
    return Notification::sent($user, PromotionActivityNotification::class)
        ->map(fn (PromotionActivityNotification $notification): string => $notification->notice->value)
        ->all();
}

function boughtPromotion(object $test, PromotionPaymentMethod $method = PromotionPaymentMethod::BANK_TRANSFER): AdPromotion
{
    return AdPromotion::factory()->create([
        'ad_id' => $test->ad->id,
        'user_id' => $test->owner->id,
        'payment_method' => $method->value,
        'status' => PromotionStatus::ACTIVE->value,
    ]);
}

it('tells the seller when an admin confirmed the transfer, once', function (): void {
    $promotion = boughtPromotion($this);

    PromotionActivated::dispatch($promotion);

    expect(promotionNoticesFor($this->owner))->toBe([PromotionNotice::ACTIVATED->value]);
});

it('does not announce a wallet purchase the seller made a moment ago', function (): void {
    PromotionActivated::dispatch(boughtPromotion($this, PromotionPaymentMethod::WALLET));

    expect(promotionNoticesFor($this->owner))->toBe([]);
});

it('tells the seller about an expiry and a rejected transfer', function (): void {
    $promotion = boughtPromotion($this);

    PromotionExpired::dispatch($promotion);
    PromotionRejected::dispatch($promotion);

    expect(promotionNoticesFor($this->owner))->toBe([PromotionNotice::EXPIRED->value, PromotionNotice::REJECTED->value]);
});

it('keeps the ad title when the ad is gone before the notice is built', function (): void {
    $promotion = boughtPromotion($this);
    $this->ad->delete();

    PromotionExpired::dispatch($promotion);

    expect(Notification::sent($this->owner, PromotionActivityNotification::class)->first()->adTitle)->toBe('Blue bike');
});

it('delivers on the notifications queue and leaves out the channels the seller muted', function (): void {
    $seller = User::factory()->create([
        'notification_preferences' => NotificationPreferences::defaults()->with(email: ['listing_updates' => false]),
    ]);
    $notification = new PromotionActivityNotification(PromotionNotice::EXPIRED, boughtPromotion($this), 'Blue bike');

    expect($notification->via($seller))->toBe(['database'])
        ->and($notification->via($this->owner))->toBe(['mail', 'database'])
        ->and($notification->viaQueues()['mail'])->toBe(QueueName::NOTIFICATIONS->value);
});

it('notifies exactly once through the real lifecycle when the transfer is confirmed twice', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $lifecycle = app(PromotionLifecycleService::class);
    $promotion = $lifecycle->purchase($this->owner, $this->ad, PromotionType::HIGHLIGHT, PromotionPaymentMethod::BANK_TRANSFER, 'TRX-1');

    $lifecycle->confirmTransfer($promotion, $admin);
    expect(fn () => $lifecycle->confirmTransfer($promotion, $admin))->toThrow(DomainException::class);

    expect(promotionNoticesFor($this->owner))->toBe([PromotionNotice::ACTIVATED->value]);
});
