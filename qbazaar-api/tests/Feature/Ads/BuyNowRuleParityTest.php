<?php

declare(strict_types=1);

use App\Data\Catalog\AdFeedFilters;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Ad;
use App\Models\User;
use App\Services\PurchaseRequests\PurchaseRequestTransitionService;
use App\Services\Search\AdListingFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

/*
 * One Buy Now rule, three readers: the model (search index flag), the SQL
 * twin (database filter and facet) and the purchase-request guard. The same
 * fixtures go through all three and must agree.
 */

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->create();
});

dataset('buy now fixtures', function (): array {
    $fixtures = [];

    foreach (['offering', 'wanted'] as $adType) {
        foreach (['fixed', 'negotiable', 'free', 'contact'] as $priceType) {
            foreach ([null, '0.00', '25.00'] as $price) {
                foreach ([0, 1, 3] as $quantity) {
                    $fixtures["$adType/$priceType/" . ($price ?? 'null') . "/qty $quantity"] = [$adType, $priceType, $price, $quantity];
                }
            }
        }
    }

    return $fixtures;
});

it('agrees across the model, the SQL filter and the purchase guard', function (string $adType, string $priceType, ?string $price, int $quantity): void {
    $ad = $this->makeAd($this->seller, [
        'status' => 'active',
        'published_at' => now(),
        'ad_type' => $adType,
        'price_type' => $priceType,
        'price' => $price,
        'quantity' => $quantity,
    ]);
    $ad = Ad::query()->findOrFail($ad->id);

    $inSql = app(AdListingFilters::class)
        ->apply(Ad::query()->publiclyListed(), new AdFeedFilters(buyNow: true))
        ->whereKey($ad->id)
        ->exists();

    $guard = app(PurchaseRequestTransitionService::class);
    $guardAllows = function () use ($guard, $ad): bool {
        try {
            $guard->assertAdIsPurchasable($ad);
            $guard->assertQuantityAvailable($ad, 1);

            return true;
        } catch (DomainException $e) {
            expect($e->errorCode)->toBeIn([ErrorCode::PURCHASE_REQUEST_AD_NOT_AVAILABLE, ErrorCode::PURCHASE_QUANTITY_UNAVAILABLE]);

            return false;
        }
    };

    expect($ad->acceptsBuyNow())->toBe($inSql)
        ->and($guardAllows())->toBe($ad->acceptsBuyNow())
        ->and($ad->toSearchableArray()['buy_now'])->toBe($ad->acceptsBuyNow());
})->with('buy now fixtures');
