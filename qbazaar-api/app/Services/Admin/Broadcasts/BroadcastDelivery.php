<?php

declare(strict_types=1);

namespace App\Services\Admin\Broadcasts;

use App\Enums\BroadcastStatus;
use App\Enums\UserStatus;
use App\Jobs\Broadcasts\DeliverBroadcastChunkJob;
use App\Models\StaffBroadcast;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Delivers a broadcast in chunks spread over time, so a message to every
 * user never floods the notifications queue that also carries sign-in codes
 * and order updates. Each recipient is claimed once per broadcast in the
 * cache, so a retried chunk never notifies anyone twice.
 */
class BroadcastDelivery
{
    private const int CLAIM_DAYS = 7;

    public function __construct(private readonly BroadcastAudience $audience) {}

    /**
     * Splits the audience into delayed chunk jobs. Only the first run for a
     * queued broadcast does anything.
     */
    public function fanOut(StaffBroadcast $broadcast): void
    {
        $started = StaffBroadcast::query()
            ->whereKey($broadcast->id)
            ->where('status', BroadcastStatus::QUEUED->value)
            ->update(['status' => BroadcastStatus::SENDING->value, 'started_at' => Carbon::now()]);

        if ($started === 0) {
            return;
        }

        $spacing = (int) config('qbazaar.admin.broadcasts.chunk_spacing_seconds');
        $chunks = 0;
        $recipients = 0;

        $this->audience->query($broadcast)
            ->select('id')
            ->chunkById((int) config('qbazaar.admin.broadcasts.chunk_size'), function (Collection $users) use ($broadcast, $spacing, &$chunks, &$recipients): void {
                /** @var list<string> $ids */
                $ids = $users->modelKeys();

                DeliverBroadcastChunkJob::dispatch($broadcast->id, $ids)->delay(Carbon::now()->addSeconds($chunks * $spacing));

                $chunks++;
                $recipients += count($ids);
            });

        StaffBroadcast::query()->whereKey($broadcast->id)->update(['chunks_total' => $chunks, 'recipients_count' => $recipients]);

        $this->completeIfDone($broadcast->id);
    }

    /**
     * Notifies one chunk of the audience: still-active accounts that were not
     * notified by an earlier attempt of this chunk.
     *
     * @param list<string> $userIds
     */
    public function deliverChunk(StaffBroadcast $broadcast, array $userIds): void
    {
        if ($broadcast->status !== BroadcastStatus::SENDING) {
            return;
        }

        $recipients = User::query()
            ->whereKey($userIds)
            ->where('status', UserStatus::ACTIVE->value)
            ->with('deviceTokens:id,user_id')
            ->get()
            ->filter(fn (User $user): bool => Cache::add($this->claimKey($broadcast, $user), true, Carbon::now()->addDays(self::CLAIM_DAYS)))
            ->values();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, $broadcast->toNotification());
        }

        StaffBroadcast::query()->whereKey($broadcast->id)->toBase()->incrementEach(
            ['delivered_count' => $recipients->count(), 'chunks_done' => 1],
            ['updated_at' => Carbon::now()],
        );

        $this->completeIfDone($broadcast->id);
    }

    /** A chunk or the fan-out gave up for good: some users may have missed it. */
    public function markFailed(string $broadcastId): void
    {
        StaffBroadcast::query()
            ->whereKey($broadcastId)
            ->whereIn('status', [BroadcastStatus::QUEUED->value, BroadcastStatus::SENDING->value])
            ->update(['status' => BroadcastStatus::FAILED->value, 'completed_at' => Carbon::now()]);
    }

    /**
     * The fan-out and every chunk try this; the conditional write lets only
     * the last of them mark the broadcast sent.
     */
    private function completeIfDone(string $broadcastId): void
    {
        StaffBroadcast::query()
            ->whereKey($broadcastId)
            ->where('status', BroadcastStatus::SENDING->value)
            ->whereNotNull('chunks_total')
            ->whereColumn('chunks_done', '>=', 'chunks_total')
            ->update(['status' => BroadcastStatus::SENT->value, 'completed_at' => Carbon::now()]);
    }

    private function claimKey(StaffBroadcast $broadcast, User $user): string
    {
        return "admin:broadcast:{$broadcast->id}:{$user->id}";
    }
}
