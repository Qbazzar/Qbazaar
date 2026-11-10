<?php

declare(strict_types=1);

namespace Database\Seeders\Demo\Content;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\User;
use Database\Seeders\Demo\DemoOptions;
use Illuminate\Database\Eloquent\Collection;

/**
 * Keeps the listings of demo accounts alive. Ads expire after their
 * lifetime like any other, which left a demo site empty; this gives them a
 * fresh expiry, can bring expired ones back, and can spread publication
 * over the last few days (a handful inside the last 24 hours) so the home
 * "Recommended" rail, which shows promoted and just-published ads, is full.
 *
 * It writes with plain updates, so no events, notifications or mails fire,
 * and it only reaches ads owned by demo accounts.
 */
final class DemoAdRenewer
{
    private const int CHUNK = 200;

    /** Generated ads are published somewhere in the last five days. */
    private const int STAGGER_MINUTES = 5 * 24 * 60;

    public function __construct(
        private readonly DesignManifest $design,
    ) {}

    /**
     * @param bool $reviveExpired bring expired ads back to live
     * @param bool $restagger move publication dates to the last days (design ads get the age the design shows)
     * @return array{extended: int, revived: int, ids: list<string>} ids of the ads that changed (or would)
     */
    public function renew(bool $dryRun, int $days, bool $reviveExpired, bool $restagger): array
    {
        $result = ['extended' => 0, 'revived' => 0, 'ids' => []];
        $statuses = $reviveExpired ? [AdStatus::ACTIVE, AdStatus::EXPIRED] : [AdStatus::ACTIVE];
        $designAges = $this->designAges();

        Ad::query()
            ->whereIn('user_id', User::query()->select('id')->where('email', 'like', '%@' . DemoOptions::EMAIL_DOMAIN))
            ->whereIn('status', array_map(static fn (AdStatus $status): string => $status->value, $statuses))
            ->chunkById(self::CHUNK, function (Collection $ads) use ($dryRun, $days, $restagger, $designAges, &$result): void {
                foreach ($ads as $ad) {
                    $changes = $this->changesFor($ad, $days, $restagger, $designAges);

                    if ($changes === []) {
                        continue;
                    }

                    $ad->status === AdStatus::EXPIRED ? $result['revived']++ : $result['extended']++;
                    $result['ids'][] = $ad->id;

                    if (! $dryRun) {
                        Ad::query()->whereKey($ad->id)->update($changes);
                    }
                }
            });

        return $result;
    }

    /**
     * @param array<string, int> $designAges minutes since publication per design title
     * @return array<string, mixed>
     */
    private function changesFor(Ad $ad, int $days, bool $restagger, array $designAges): array
    {
        $revive = $ad->status === AdStatus::EXPIRED;
        $needsExpiry = $revive || $restagger || $ad->expires_at === null || $ad->expires_at->lt(now()->addDays(intdiv($days, 2)));

        if (! $needsExpiry) {
            return [];
        }

        $changes = ['status' => AdStatus::ACTIVE->value, 'expires_at' => now()->addDays($days), 'expiring_notified_at' => null];

        if ($restagger || $revive) {
            $changes['published_at'] = now()->subMinutes($designAges[$ad->title] ?? crc32($ad->id) % self::STAGGER_MINUTES);
        }

        return $changes;
    }

    /**
     * @return array<string, int>
     */
    private function designAges(): array
    {
        $ages = [];

        foreach ($this->design->ads() as $ad) {
            $ages[$ad['title']['en']] = $ad['posted_minutes_ago'];
            $ages[$ad['title']['ar']] = $ad['posted_minutes_ago'];
        }

        return $ages;
    }
}
