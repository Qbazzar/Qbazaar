<?php

declare(strict_types=1);

use App\Actions\Reviews\CreateReviewAction;
use App\Enums\AdStatus;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\Offer;
use App\Models\Review;
use App\Models\User;
use App\Services\Reviews\ReviewEligibilityService;
use Database\Seeders\CategorySeeder;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['scout.driver' => null]);
    seed([CategorySeeder::class, LocationSeeder::class]);
});

function activeAdOf(User $seller): Ad
{
    return Ad::factory()->create(['user_id' => $seller->id, 'status' => AdStatus::ACTIVE->value]);
}

function closeDealOn(Ad $ad, User $buyer): void
{
    $conversation = Conversation::query()->create([
        'ad_id' => $ad->id,
        'buyer_id' => $buyer->id,
        'seller_id' => $ad->user_id,
    ]);

    Offer::factory()->accepted()->create([
        'conversation_id' => $conversation->id,
        'ad_id' => $ad->id,
        'buyer_id' => $buyer->id,
        'seller_id' => $ad->user_id,
    ]);
}

it('lets a buyer with a closed deal review the seller and refreshes the rating', function (): void {
    $seller = User::factory()->create();
    $buyer = User::factory()->create();
    $ad = activeAdOf($seller);
    closeDealOn($ad, $buyer);
    Sanctum::actingAs($buyer, ['*']);

    postJson("/api/v1/ads/{$ad->id}/reviews", ['rating' => 4, 'comment' => 'Smooth deal'])
        ->assertCreated()
        ->assertJsonPath('data.rating', 4)
        ->assertJsonPath('data.comment', 'Smooth deal')
        ->assertJsonPath('data.reviewer.id', $buyer->id)
        ->assertJsonPath('data.ad.id', $ad->id);

    expect($seller->fresh()?->rating_count)->toBe(1);
});

it('refuses a review on the reviewer\'s own ad', function (): void {
    $seller = User::factory()->create();
    $ad = activeAdOf($seller);
    Sanctum::actingAs($seller, ['*']);

    postJson("/api/v1/ads/{$ad->id}/reviews", ['rating' => 5])
        ->assertJsonPath('error.code', ErrorCode::REVIEW_OWN_AD->value);
});

it('refuses a review without an accepted offer', function (): void {
    $ad = activeAdOf(User::factory()->create());
    Sanctum::actingAs(User::factory()->create(), ['*']);

    postJson("/api/v1/ads/{$ad->id}/reviews", ['rating' => 5])
        ->assertJsonPath('error.code', ErrorCode::REVIEW_NOT_ELIGIBLE->value);

    expect(Review::query()->count())->toBe(0);
});

it('refuses a second review of the same ad', function (): void {
    $buyer = User::factory()->create();
    $ad = activeAdOf(User::factory()->create());
    closeDealOn($ad, $buyer);
    Sanctum::actingAs($buyer, ['*']);

    postJson("/api/v1/ads/{$ad->id}/reviews", ['rating' => 5])->assertCreated();
    postJson("/api/v1/ads/{$ad->id}/reviews", ['rating' => 1])
        ->assertJsonPath('error.code', ErrorCode::REVIEW_ALREADY_EXISTS->value);

    expect(Review::query()->count())->toBe(1);
});

it('maps a concurrent duplicate insert to REVIEW_ALREADY_EXISTS', function (): void {
    $buyer = User::factory()->create();
    $ad = activeAdOf(User::factory()->create());
    closeDealOn($ad, $buyer);
    app(CreateReviewAction::class)->execute($buyer, $ad, 5, null);

    // Skips the pre-check, as a request racing the first one would.
    $racingRequest = new CreateReviewAction(new class extends ReviewEligibilityService
    {
        public function ensureCanReview(User $reviewer, Ad $ad): void {}
    });

    $errorCode = null;
    try {
        $racingRequest->execute($buyer, $ad, 1, null);
    } catch (DomainException $exception) {
        $errorCode = $exception->errorCode;
    }

    expect($errorCode)->toBe(ErrorCode::REVIEW_ALREADY_EXISTS)
        ->and(Review::query()->count())->toBe(1);
});

it('lists reviews whose ad and reviewer were soft-deleted, in a fixed number of queries', function (): void {
    $seller = User::factory()->create();
    $deletedBuyer = User::factory()->create();
    $deletedAd = activeAdOf($seller);
    closeDealOn($deletedAd, $deletedBuyer);
    app(CreateReviewAction::class)->execute($deletedBuyer, $deletedAd, 5, 'Great');

    $otherBuyer = User::factory()->create();
    $otherAd = activeAdOf($seller);
    closeDealOn($otherAd, $otherBuyer);
    app(CreateReviewAction::class)->execute($otherBuyer, $otherAd, 3, null);

    $deletedAd->delete();
    $deletedBuyer->delete();

    DB::enableQueryLog();

    getJson("/api/v1/users/{$seller->id}/reviews")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['id' => $deletedAd->id, 'title' => $deletedAd->title])
        ->assertJsonFragment(['id' => $deletedBuyer->id, 'full_name' => $deletedBuyer->full_name]);

    // count + page + reviewers + ads
    expect(DB::getQueryLog())->toHaveCount(4);
});
