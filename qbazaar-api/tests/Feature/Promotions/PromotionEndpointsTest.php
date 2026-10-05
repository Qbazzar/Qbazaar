<?php

declare(strict_types=1);

use App\Data\Ledger\LedgerActor;
use App\Data\Ledger\LedgerReference;
use App\Enums\AdStatus;
use App\Enums\LedgerAccountType;
use App\Enums\LedgerReferenceType;
use App\Enums\LedgerTransactionType;
use App\Enums\PlatformSetting;
use App\Enums\PromotionStatus;
use App\Events\Promotions\PromotionActivated;
use App\Exceptions\ErrorCode;
use App\Models\Ad;
use App\Models\AdPromotion;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Services\Ledger\LedgerAccounts;
use App\Services\Ledger\LedgerRecipes;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesAds;
use Tests\Concerns\ManagesMoney;

uses(RefreshDatabase::class, CreatesAds::class, ManagesMoney::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->owner = User::factory()->create();
    $this->ad = $this->makeAd($this->owner, ['status' => AdStatus::ACTIVE->value]);
    config([
        'qbazaar.promotions.types.premium.price' => '50.00',
        'qbazaar.promotions.types.premium.days' => 14,
    ]);
});

function fundWallet(User $user, string $amount): void
{
    app(LedgerRecipes::class)->adjustWallet($user->id, $amount, 'top up', new LedgerReference(LedgerReferenceType::ADJUSTMENT, (string) Str::ulid()), LedgerActor::system());
}

function buyPromotion(User $actor, Ad $ad, array $payload, ?string $idempotencyKey = null)
{
    return test()->actingAs($actor, 'sanctum')->postJson(
        '/api/v1/ads/' . $ad->id . '/promotions',
        $payload,
        $idempotencyKey === null ? [] : ['X-Idempotency-Key' => $idempotencyKey],
    );
}

it('lists every promotion type with its admin-set price and duration', function (): void {
    app(SettingsService::class)->put(PlatformSetting::PROMOTION_HIGHLIGHT_PRICE, '12.50', User::factory()->create());

    $this->getJson('/api/v1/promotions')
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('data.0', ['type' => 'highlight', 'price' => '12.50', 'currency' => 'QAR', 'duration_days' => 7])
        ->assertJsonPath('data.3', ['type' => 'premium', 'price' => '50.00', 'currency' => 'QAR', 'duration_days' => 14]);
});

it('activates a wallet-paid promotion at once and books it in the same step', function (): void {
    Event::fake([PromotionActivated::class]);
    fundWallet($this->owner, '80.00');

    buyPromotion($this->owner, $this->ad, ['type' => 'premium', 'payment_method' => 'wallet'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.price', '50.00')
        ->assertJsonPath('data.duration_days', 14);

    $promotion = AdPromotion::query()->sole();
    $accounts = app(LedgerAccounts::class);

    expect($promotion->ends_at->toDateString())->toBe(now()->addDays(14)->toDateString())
        ->and($this->ad->fresh()->promotion_rank)->toBe(4)
        ->and($accounts->balanceOf(LedgerAccountType::USER_WALLET, $this->owner->id))->toBe('30.00')
        ->and($accounts->balanceOf(LedgerAccountType::PLATFORM_REVENUE))->toBe('50.00')
        ->and(LedgerTransaction::query()->where('type', LedgerTransactionType::PROMOTION_PURCHASED->value)->sole()->reference_id)->toBe($promotion->id);

    Event::assertDispatched(PromotionActivated::class);
});

it('refuses a wallet purchase the balance cannot cover and keeps nothing', function (): void {
    fundWallet($this->owner, '10.00');

    buyPromotion($this->owner, $this->ad, ['type' => 'premium', 'payment_method' => 'wallet'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', ErrorCode::WALLET_INSUFFICIENT_BALANCE->value);

    expect(AdPromotion::query()->count())->toBe(0)
        ->and($this->ad->fresh()->promotion_rank)->toBe(0)
        ->and(app(LedgerAccounts::class)->balanceOf(LedgerAccountType::USER_WALLET, $this->owner->id))->toBe('10.00');
});

it('refuses a wallet purchase larger than the wallet minus the commission owed and posts nothing', function (): void {
    fundWallet($this->owner, '80.00');
    $this->owesCommission($this->owner, '40.00');
    $entriesBefore = LedgerTransaction::query()->count();

    buyPromotion($this->owner, $this->ad, ['type' => 'premium', 'payment_method' => 'wallet'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', ErrorCode::WALLET_EXCEEDS_WITHDRAWABLE->value)
        ->assertJsonPath('error.details.withdrawable', '40.00');

    expect(AdPromotion::query()->count())->toBe(0)
        ->and($this->ad->fresh()->promotion_rank)->toBe(0)
        ->and(LedgerTransaction::query()->count())->toBe($entriesBefore)
        ->and($this->balanceOf(LedgerAccountType::USER_WALLET, $this->owner))->toBe('80.00')
        ->and($this->balanceOf(LedgerAccountType::USER_COMMISSION_RECEIVABLE, $this->owner))->toBe('40.00');
});

it('allows a wallet purchase up to the withdrawable amount and leaves the debt alone', function (): void {
    fundWallet($this->owner, '90.00');
    $this->owesCommission($this->owner, '40.00');

    buyPromotion($this->owner, $this->ad, ['type' => 'premium', 'payment_method' => 'wallet'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'active');

    expect($this->balanceOf(LedgerAccountType::USER_WALLET, $this->owner))->toBe('40.00')
        ->and($this->balanceOf(LedgerAccountType::USER_COMMISSION_RECEIVABLE, $this->owner))->toBe('40.00');
});

it('lets a bank-transfer promotion be bought by someone who owes commission', function (): void {
    $this->owesCommission($this->owner, '40.00');

    buyPromotion($this->owner, $this->ad, ['type' => 'premium', 'payment_method' => 'bank_transfer', 'transfer_reference' => 'TRX-9'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending_payment');
});

it('leaves a bank-transfer promotion pending with nothing booked', function (): void {
    buyPromotion($this->owner, $this->ad, ['type' => 'highlight', 'payment_method' => 'bank_transfer', 'transfer_reference' => 'TRX-2049'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending_payment')
        ->assertJsonPath('data.transfer_reference', 'TRX-2049')
        ->assertJsonPath('data.starts_at', null);

    expect(LedgerTransaction::query()->count())->toBe(0)
        ->and($this->ad->fresh()->promotion_rank)->toBe(0);
});

it('charges once when the same promotion is bought twice', function (): void {
    fundWallet($this->owner, '200.00');

    buyPromotion($this->owner, $this->ad, ['type' => 'premium', 'payment_method' => 'wallet'])->assertCreated();
    buyPromotion($this->owner, $this->ad, ['type' => 'premium', 'payment_method' => 'wallet'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', ErrorCode::PROMOTION_ALREADY_OPEN->value);

    expect(app(LedgerAccounts::class)->balanceOf(LedgerAccountType::USER_WALLET, $this->owner->id))->toBe('150.00');
});

it('replays a retried purchase with the same idempotency key', function (): void {
    fundWallet($this->owner, '200.00');
    $key = '01HZY7Q4C9V3N8B2K6M5T1R0PR';

    buyPromotion($this->owner, $this->ad, ['type' => 'premium', 'payment_method' => 'wallet'], $key)->assertCreated();
    buyPromotion($this->owner, $this->ad, ['type' => 'premium', 'payment_method' => 'wallet'], $key)
        ->assertCreated()
        ->assertHeader('X-Idempotent-Replay', 'true');

    expect(AdPromotion::query()->count())->toBe(1);
});

it('lets the ad hold promotions of different types', function (): void {
    fundWallet($this->owner, '200.00');

    buyPromotion($this->owner, $this->ad, ['type' => 'highlight', 'payment_method' => 'wallet'])->assertCreated();
    buyPromotion($this->owner, $this->ad, ['type' => 'gallery', 'payment_method' => 'wallet'])->assertCreated();

    expect($this->ad->fresh()->promotion_rank)->toBe(3);
});

it('hides other people\'s ads behind AD_001', function (): void {
    $stranger = User::factory()->create();
    fundWallet($stranger, '100.00');

    buyPromotion($stranger, $this->ad, ['type' => 'highlight', 'payment_method' => 'wallet'])
        ->assertNotFound()
        ->assertJsonPath('error.code', ErrorCode::AD_NOT_FOUND->value);

    expect(AdPromotion::query()->count())->toBe(0);
});

it('only promotes a live ad', function (): void {
    $draft = $this->makeAd($this->owner, ['status' => AdStatus::DRAFT->value]);

    buyPromotion($this->owner, $draft, ['type' => 'highlight', 'payment_method' => 'bank_transfer', 'transfer_reference' => 'TRX-1'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', ErrorCode::AD_NOT_ACTIVE->value);
});

it('validates the purchase', function (array $payload, array $errors): void {
    buyPromotion($this->owner, $this->ad, $payload)
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED')
        ->assertJsonValidationErrors($errors, 'error.details');
})->with([
    'unknown type' => [['type' => 'banner', 'payment_method' => 'wallet'], ['type']],
    'unknown method' => [['type' => 'premium', 'payment_method' => 'card'], ['payment_method']],
    'transfer without reference' => [['type' => 'premium', 'payment_method' => 'bank_transfer'], ['transfer_reference']],
    'reference with markup' => [['type' => 'premium', 'payment_method' => 'bank_transfer', 'transfer_reference' => '<b>1</b>'], ['transfer_reference']],
]);

it('requires sign-in to buy', function (): void {
    $this->postJson('/api/v1/ads/' . $this->ad->id . '/promotions', ['type' => 'premium', 'payment_method' => 'wallet'])
        ->assertUnauthorized();
});

it('lists the caller\'s promotions newest first', function (): void {
    AdPromotion::factory()->create(['ad_id' => $this->ad->id, 'user_id' => $this->owner->id, 'created_at' => now()->subDay()]);
    $newest = AdPromotion::factory()->create(['ad_id' => $this->ad->id, 'user_id' => $this->owner->id, 'type' => 'premium']);
    AdPromotion::factory()->create();

    $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/account/promotions')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $newest->id)
        ->assertJsonPath('data.0.ad_title', $this->ad->title)
        ->assertJsonPath('data.0.status', PromotionStatus::PENDING_PAYMENT->value);
});

it('shows the strongest promotion on the ad', function (): void {
    $this->ad->forceFill(['promotion_rank' => 4])->save();

    $this->getJson('/api/v1/ads/' . $this->ad->id)
        ->assertOk()
        ->assertJsonPath('data.promotion', 'premium');
});
