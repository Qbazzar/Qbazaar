<?php

declare(strict_types=1);

namespace Database\Seeders\Demo\Steps;

use App\Actions\Ads\ToggleAdFeaturedAction;
use App\Enums\AdShipping;
use App\Enums\AdStatus;
use App\Enums\Condition;
use App\Enums\LocationType;
use App\Models\Ad;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use App\Services\Admin\AdminAuditLogger;
use App\Services\Ads\AdLifecycleService;
use App\Services\Ads\CustomFieldsValidator;
use Closure;
use Database\Seeders\Demo\Catalog\Listings;
use Database\Seeders\Demo\Catalog\Phrases;
use Database\Seeders\Demo\DemoContext;
use Database\Seeders\Demo\DemoMedia;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Creates the listings with photos and walks each one to its final status
 * through AdLifecycleService, at believable moments in the past. Reserved
 * and sold-through-an-order listings are produced later by the deals, from
 * the active ones created here.
 */
final class SeedListings
{
    /**
     * Share of listings per final status; ACTIVE takes whatever is left.
     * "sold" here means sold outside the platform; the deals add more.
     */
    private const array STATUS_SHARES = [
        AdStatus::DRAFT->value => 0.06,
        AdStatus::PENDING->value => 0.08,
        AdStatus::REJECTED->value => 0.05,
        AdStatus::BLOCKED->value => 0.03,
        AdStatus::EXPIRED->value => 0.08,
        AdStatus::SOLD->value => 0.05,
    ];

    private const float FEATURED_SHARE = 0.08;

    private const int LIFETIME_DAYS = 30;

    /** @var Collection<int, Category> */
    private Collection $categories;

    /** @var Collection<int, Location> */
    private Collection $districts;

    public function __construct(
        private readonly Listings $listings,
        private readonly AdLifecycleService $lifecycle,
        private readonly CustomFieldsValidator $fieldsValidator,
        private readonly ToggleAdFeaturedAction $toggleFeatured,
        private readonly AdminAuditLogger $audit,
        private readonly DemoMedia $media,
    ) {}

    public function run(DemoContext $context): void
    {
        $this->loadTaxonomy();

        foreach ($this->statusPlan($context->options->ads) as $status) {
            $this->createListing($context, $status);
        }
    }

    /**
     * @return list<AdStatus> one entry per listing, every status present when there is room
     */
    private function statusPlan(int $total): array
    {
        $plan = [];

        foreach (self::STATUS_SHARES as $status => $share) {
            $count = $total >= count(self::STATUS_SHARES) * 2 ? max(1, (int) round($total * $share)) : 0;
            array_push($plan, ...array_fill(0, $count, AdStatus::from($status)));
        }

        return [...$plan, ...array_fill(0, max(0, $total - count($plan)), AdStatus::ACTIVE)];
    }

    private function loadTaxonomy(): void
    {
        $this->categories = Category::query()
            ->whereIn('slug', $this->listings->categorySlugs())
            ->whereNotNull('parent_id')
            ->with('parent:id,slug')
            ->get();

        $this->districts = Location::query()->where('type', LocationType::DISTRICT->value)->get();

        if ($this->categories->isEmpty() || $this->districts->isEmpty()) {
            throw new RuntimeException('Categories and locations are missing. Run with --fresh or seed the reference data first.');
        }
    }

    private function createListing(DemoContext $context, AdStatus $status): void
    {
        $random = $context->random;
        $seller = $this->pickSeller($context);
        $category = $random->pick($this->categories->all());
        $district = $random->pick($this->districts->all());
        $parentSlug = $category->parent?->slug;
        $draft = $this->listings->draft($category->slug, (string) $parentSlug, $seller->language->value, $district->getLocalizedName($seller->language->value));
        $createdAt = $this->createdAtFor($context, $status);

        $ad = $context->clock->at($createdAt, fn (): Ad => DB::transaction(function () use ($context, $seller, $category, $district, $draft, $status, $parentSlug): Ad {
            $ad = new Ad;
            $ad->forceFill([
                'user_id' => $seller->id,
                'category_id' => $category->id,
                'location_id' => $district->id,
                'latitude' => $this->jitter($district->lat, $context),
                'longitude' => $this->jitter($district->lng, $context),
                'title' => $draft->title,
                'description' => $draft->description,
                'price' => $draft->price,
                'price_type' => $draft->priceType,
                'currency' => 'QAR',
                'condition' => $draft->hasCondition ? $context->random->pick(Condition::cases()) : null,
                'shipping' => $context->random->chance(0.3) ? AdShipping::DELIVERY : AdShipping::PICKUP_ONLY,
                'custom_fields' => $draft->customFields === null ? null : $this->fieldsValidator->validate($category, $draft->customFields),
                'status' => $status === AdStatus::DRAFT ? AdStatus::DRAFT : AdStatus::PENDING,
                'submitted_at' => $status === AdStatus::DRAFT ? null : now(),
            ])->save();

            $this->media->attachListingPhotos($ad, $this->listings->colorFor($parentSlug), $draft->captionEn, $this->photoCount($context));

            return $ad;
        }));

        $this->moveTo($context, $ad, $status, $createdAt);
    }

    private function moveTo(DemoContext $context, Ad $ad, AdStatus $status, Carbon $createdAt): void
    {
        $reviewedAt = $createdAt->copy()->addHours($context->random->int(1, 20));
        $moderator = $context->moderator();

        match ($status) {
            AdStatus::DRAFT, AdStatus::PENDING => null,
            AdStatus::REJECTED => $context->clock->at($reviewedAt, function () use ($ad, $moderator, $context): void {
                $notes = $context->random->pick(Phrases::REJECTION_NOTES);
                $this->lifecycle->reject($ad, $notes);
                $this->audit->record($moderator, 'admin.ads.reject', $ad, ['input' => ['admin_notes' => $notes]]);
            }),
            AdStatus::ACTIVE => $this->goLive($context, $ad, $reviewedAt),
            AdStatus::EXPIRED => $this->expire($context, $ad, $reviewedAt),
            AdStatus::BLOCKED => $this->goLiveThen($context, $ad, $reviewedAt, function () use ($ad, $moderator): void {
                $this->lifecycle->block($ad);
                $this->audit->record($moderator, 'admin.ads.suspend', $ad);
            }),
            AdStatus::SOLD => $this->goLiveThen($context, $ad, $reviewedAt, fn () => $this->lifecycle->markSold($ad)),
        };
    }

    private function goLive(DemoContext $context, Ad $ad, Carbon $at): void
    {
        $context->clock->at($at, fn () => $this->lifecycle->approve($ad));

        $ad->forceFill(['views_count' => $context->random->int(15, 2_500)])->saveQuietly();

        if ($context->random->chance(self::FEATURED_SHARE)) {
            ($this->toggleFeatured)($ad);
            $this->audit->record($context->moderator(), 'admin.ads.feature', $ad);
        }
    }

    private function goLiveThen(DemoContext $context, Ad $ad, Carbon $at, Closure $then): void
    {
        $this->goLive($context, $ad, $at);
        $context->clock->at($at->copy()->addDays($context->random->int(1, 8)), $then);
    }

    private function expire(DemoContext $context, Ad $ad, Carbon $at): void
    {
        $this->goLive($context, $ad, $at);
        $context->clock->at($at->copy()->addDays(self::LIFETIME_DAYS)->addHour(), fn () => $this->lifecycle->expireIfPastDue($ad));
    }

    /**
     * Listings that end up expired had to be published more than a
     * lifetime ago; everything else is spread over the last few weeks.
     */
    private function createdAtFor(DemoContext $context, AdStatus $status): Carbon
    {
        $days = $status === AdStatus::EXPIRED
            ? $context->random->int(self::LIFETIME_DAYS + 3, self::LIFETIME_DAYS + 25)
            : $context->random->int(1, 24);

        return $context->clock->daysAgo($days, $context->random->int(0, 1_200));
    }

    /**
     * Business accounts list three times as much as private ones.
     */
    private function pickSeller(DemoContext $context): User
    {
        $member = $context->random->pick($context->members);

        if (! $member->isBusiness() && $context->random->chance(0.5)) {
            return $context->random->pick($context->members);
        }

        return $member;
    }

    private function photoCount(DemoContext $context): int
    {
        return $context->random->pick([1, 2, 3, 3, 4, 4, 4, 5, 5, 6, 7, 8]);
    }

    private function jitter(?string $coordinate, DemoContext $context): ?string
    {
        if ($coordinate === null) {
            return null;
        }

        return number_format((float) $coordinate + $context->random->int(-80, 80) / 10_000, 7, '.', '');
    }
}
