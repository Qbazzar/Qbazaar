<?php

declare(strict_types=1);

namespace App\Services\Admin\Broadcasts;

use App\Enums\BroadcastStatus;
use App\Jobs\Broadcasts\FanOutBroadcastJob;
use App\Models\StaffBroadcast;
use App\Models\User;
use App\Services\Admin\AdminAuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves a staff broadcast and queues its delivery. Each sender is capped per
 * hour and per day; the check and the insert run under a per-sender lock so
 * a double submit cannot slip past the cap.
 */
class BroadcastComposer
{
    private const int LOCK_SECONDS = 10;

    public function __construct(private readonly AdminAuditLogger $audit) {}

    /**
     * @throws ValidationException when the sender reached a cap
     */
    public function compose(User $sender, BroadcastDraft $draft): StaffBroadcast
    {
        return Cache::lock('admin:broadcasts:sender:' . $sender->id, self::LOCK_SECONDS)
            ->block(self::LOCK_SECONDS, function () use ($sender, $draft): StaffBroadcast {
                $this->ensureWithinLimits($sender);

                return DB::transaction(fn (): StaffBroadcast => $this->store($sender, $draft));
            });
    }

    /**
     * How many broadcasts the sender may still send this hour and today.
     *
     * @return array{hour: int, day: int}
     */
    public function remaining(User $sender): array
    {
        $sent = $this->recentCounts($sender);

        return [
            'hour' => max(0, $this->limit('max_per_hour') - $sent['hour']),
            'day' => max(0, $this->limit('max_per_day') - $sent['day']),
        ];
    }

    private function store(User $sender, BroadcastDraft $draft): StaffBroadcast
    {
        $broadcast = new StaffBroadcast;
        $broadcast->forceFill([
            'sender_id' => $sender->id,
            'segment' => $draft->segment,
            'location_id' => $draft->segment->needsLocation() ? $draft->locationId : null,
            'title_ar' => $draft->titleAr,
            'body_ar' => $draft->bodyAr,
            'title_en' => $draft->titleEn,
            'body_en' => $draft->bodyEn,
            'send_email' => $draft->sendEmail,
            'status' => BroadcastStatus::QUEUED,
        ])->save();

        $this->audit->record($sender, 'admin.broadcasts.sent', $broadcast, [
            'segment' => $draft->segment->value,
            'location_id' => $broadcast->location_id,
            'send_email' => $draft->sendEmail,
        ]);

        FanOutBroadcastJob::dispatch($broadcast->id)->afterCommit();

        return $broadcast;
    }

    private function ensureWithinLimits(User $sender): void
    {
        $remaining = $this->remaining($sender);

        if ($remaining['hour'] === 0) {
            throw ValidationException::withMessages(['limit' => __('admin.broadcasts.errors.hourly_limit', ['max' => $this->limit('max_per_hour')])]);
        }

        if ($remaining['day'] === 0) {
            throw ValidationException::withMessages(['limit' => __('admin.broadcasts.errors.daily_limit', ['max' => $this->limit('max_per_day')])]);
        }
    }

    /**
     * One pass over the sender's last day, on staff_broadcasts_sender_created_idx.
     *
     * @return array{hour: int, day: int}
     */
    private function recentCounts(User $sender): array
    {
        $now = Carbon::now();

        $row = StaffBroadcast::query()
            ->where('sender_id', $sender->id)
            ->where('created_at', '>=', $now->copy()->subDay())
            ->selectRaw('COUNT(*) as day_total, COALESCE(SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END), 0) as hour_total', [$now->copy()->subHour()])
            ->toBase()
            ->first();

        return ['hour' => (int) ($row->hour_total ?? 0), 'day' => (int) ($row->day_total ?? 0)];
    }

    private function limit(string $key): int
    {
        return (int) config('qbazaar.admin.broadcasts.' . $key);
    }
}
