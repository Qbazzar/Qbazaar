<?php

declare(strict_types=1);

namespace App\Listeners\Search;

use App\Events\Ads\AdApproved;
use App\Events\Ads\AdPublished;
use App\Models\Ad;
use App\Models\SavedSearch;
use App\Notifications\Search\SavedSearchMatchNotification;
use App\Services\Notifications\AdAudienceNotifier;
use App\Services\Search\SavedSearchMatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;

/**
 * Alerts owners of saved searches (with alerts on) that a newly live ad
 * matches. Runs on the low queue and walks the SQL pre-filtered candidates
 * in chunks; each user is alerted once per ad, even with several matching
 * searches or when the ad is approved again after an edit.
 */
class NotifySavedSearchMatches implements ShouldQueue
{
    public string $queue = 'low';

    // Recipients are claimed once per event, so a retry only reaches the ones not alerted yet.
    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(
        private readonly SavedSearchMatcher $matcher,
        private readonly AdAudienceNotifier $notifier,
    ) {}

    public function handle(AdPublished|AdApproved $event): void
    {
        $ad = $event->ad->loadMissing('user');

        if (! $ad->isPubliclyListed()) {
            return;
        }

        $this->matcher->candidates($ad)
            ->select(['id', 'user_id', 'name', 'query_params'])
            ->chunkById((int) config('qbazaar.notifications.fan_out_chunk'), function (Collection $searches) use ($ad): void {
                $this->notifier->notifyEach($ad, $this->notificationsFor($ad, $searches), "saved-search:{$ad->id}");
            });
    }

    /**
     * @param Collection<int, SavedSearch> $searches
     * @return array<string, SavedSearchMatchNotification>
     */
    private function notificationsFor(Ad $ad, Collection $searches): array
    {
        $notifications = [];

        foreach ($searches as $search) {
            if (! isset($notifications[$search->user_id]) && $this->matcher->matchesDetails($ad, $search->query_params)) {
                $notifications[$search->user_id] = new SavedSearchMatchNotification($ad, $search->name);
            }
        }

        return $notifications;
    }
}
