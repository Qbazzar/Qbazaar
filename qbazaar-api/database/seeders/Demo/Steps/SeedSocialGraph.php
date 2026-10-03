<?php

declare(strict_types=1);

namespace Database\Seeders\Demo\Steps;

use App\Actions\Search\SaveSearchAction;
use App\Actions\Social\FollowUserAction;
use App\Models\User;
use Database\Seeders\Demo\Catalog\Phrases;
use Database\Seeders\Demo\DemoContext;

/**
 * Follows and saved searches. Seeded before the listings go live, so the
 * approvals that follow fan out "new ad from a seller you follow" and
 * saved-search alerts through the real listeners.
 */
final class SeedSocialGraph
{
    /** Alert criteria, in the same order as {@see Phrases::SAVED_SEARCH_NAMES}. */
    private const array SAVED_SEARCH_QUERIES = [
        ['category_slug' => 'cars', 'price_max' => 300_000],
        ['category_slug' => 'apartments-for-rent', 'location_slug' => 'the-pearl'],
        ['category_slug' => 'mobile-phones', 'condition' => 'like_new'],
        ['category_slug' => 'furniture', 'price_max' => 5_000],
    ];

    public function __construct(
        private readonly FollowUserAction $follow,
        private readonly SaveSearchAction $saveSearch,
    ) {}

    public function run(DemoContext $context): void
    {
        $sellerAccount = $context->members[1] ?? null;

        foreach ($context->members as $member) {
            $this->followSome($context, $member, $sellerAccount);
            $this->saveSearches($context, $member);
        }
    }

    private function followSome(DemoContext $context, User $member, ?User $sellerAccount): void
    {
        $others = array_filter($context->members, static fn (User $other): bool => $other->id !== $member->id);
        $followed = $context->random->sample($others, $context->random->int(1, 6));

        if ($sellerAccount !== null && $sellerAccount->id !== $member->id && $context->random->chance(0.6)) {
            $followed[] = $sellerAccount;
        }

        foreach ($followed as $target) {
            $context->clock->at(
                $context->clock->daysAgo($context->random->int(30, 60)),
                fn () => ($this->follow)($member, $target),
            );
        }
    }

    private function saveSearches(DemoContext $context, User $member): void
    {
        if (! $context->random->chance(0.4)) {
            return;
        }

        $names = Phrases::SAVED_SEARCH_NAMES[$member->language->value];

        foreach ($context->random->sample(array_keys(self::SAVED_SEARCH_QUERIES), $context->random->int(1, 2)) as $index) {
            $this->saveSearch->create($member, [
                'name' => $names[$index],
                'query_params' => self::SAVED_SEARCH_QUERIES[$index],
                'alerts_enabled' => $context->random->chance(0.85),
            ]);
        }
    }
}
