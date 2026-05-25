<?php

declare(strict_types=1);

namespace App\Listeners\Notifications;

use App\Events\Notifications\NotificationCreated;
use App\Models\User;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Bridges Laravel's built-in `NotificationSent` event to our own
 * `NotificationCreated` broadcast — but only for the `database` channel.
 *
 * Without this listener every successful notification delivery (mail,
 * sms, …) would ping the WebSocket, which is both noisy and incorrect:
 * the bell icon should only blink when something has been persisted that
 * the user can actually click through to.
 *
 * The newly-created Laravel notification row carries a generated UUID
 * primary key we don't have access to from the event payload; we read it
 * back from the database using the notifiable_type + notifiable_id +
 * created_at tuple. That tuple is unique-by-millisecond in practice
 * because Laravel uses `now()` at insert time and the (notifiable, read_at)
 * index covers the path.
 */
class BroadcastDatabaseNotificationCreated
{
    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'database') {
            return;
        }

        if (! $event->notifiable instanceof User) {
            return;
        }

        // `notification->id` is the per-send UUID Laravel uses for the
        // `database` channel's row PK. Available on every Notification
        // instance because Laravel assigns it in NotificationSender.
        $notificationId = $event->notification->id;

        if ($notificationId === '') {
            return;
        }

        // Load the persisted row's payload so the broadcast carries exactly
        // what the REST endpoint would return — no risk of FE drift.
        $row = DB::table('notifications')
            ->where('id', $notificationId)
            ->first(['type', 'data', 'created_at']);

        if ($row === null) {
            return;
        }

        $rawData = is_string($row->data ?? null) ? (string) $row->data : '[]';
        $decoded = json_decode($rawData, true);
        /** @var array<string, mixed> $data */
        $data = is_array($decoded) ? $decoded : [];

        $type = is_string($row->type ?? null) ? (string) $row->type : '';
        $createdAtRaw = $row->created_at ?? null;

        $createdAt = is_string($createdAtRaw) && $createdAtRaw !== ''
            ? Carbon::parse($createdAtRaw)->toIso8601String()
            : now()->toIso8601String();

        NotificationCreated::dispatch(
            $event->notifiable->id,
            $notificationId,
            $type,
            $data,
            $createdAt,
        );
    }
}
