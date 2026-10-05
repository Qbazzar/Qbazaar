<?php

declare(strict_types=1);

namespace Database\Seeders\Demo\Steps;

use App\Actions\Favorites\SetFavoriteAction;
use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\RecentView;
use App\Models\User;
use Database\Seeders\Demo\DemoContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Favourites through the real action (it keeps ads.favorites_count right)
 * and browsing history as plain chunked inserts, since a view row has no
 * side effects to preserve.
 */
final class SeedEngagement
{
    private const int INSERT_CHUNK = 500;

    public function __construct(
        private readonly SetFavoriteAction $setFavorite,
    ) {}

    public function run(DemoContext $context): void
    {
        /** @var list<Ad> $liveAds */
        $liveAds = Ad::query()->with('user')->where('status', AdStatus::ACTIVE->value)->get()->all();

        if ($liveAds === []) {
            return;
        }

        $views = [];

        foreach ($context->members as $member) {
            $others = array_filter($liveAds, static fn (Ad $ad): bool => $ad->user_id !== $member->id);

            foreach ($context->random->sample($others, $context->random->int(0, 8)) as $ad) {
                $context->clock->at($context->clock->daysAgo($context->random->int(0, 12)), fn () => $this->setFavorite->execute($member, $ad, true));
            }

            array_push($views, ...$this->browsingHistory($context, $member, $others));
        }

        DB::transaction(function () use ($views): void {
            foreach (array_chunk($views, self::INSERT_CHUNK) as $chunk) {
                RecentView::query()->insert($chunk);
            }
        });
    }

    /**
     * @param array<int, Ad> $ads
     * @return list<array{id: string, user_id: string, ad_id: string, viewed_at: string}>
     */
    private function browsingHistory(DemoContext $context, User $member, array $ads): array
    {
        return array_map(fn (Ad $ad): array => [
            'id' => (string) Str::ulid(),
            'user_id' => $member->id,
            'ad_id' => $ad->id,
            'viewed_at' => $context->clock->daysAgo($context->random->int(0, 13), $context->random->int(0, 1_400))->toDateTimeString(),
        ], $context->random->sample($ads, $context->random->int(3, 20)));
    }
}
