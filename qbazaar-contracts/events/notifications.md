# QBazaar — Notifications WebSocket Contract (Sprint 10)

Broadcast adapter: **Laravel Reverb** (Pusher-compatible).
Auth: clients call `POST /api/v1/broadcasting/auth` with their Sanctum bearer (`Authorization: Bearer <token>`). The response is the raw Pusher `{auth}` body, not the API envelope.

## Channel

`private-user.{userId}` — already declared in `routes/channels.php` for
messaging (Sprint 8). The notifications system re-uses the same channel
because every event we care about is user-scoped.

## Events

### `notification.created`

Fired the moment a `database`-channel notification is persisted for a
user (Laravel's `NotificationSent` event is bridged to our broadcast via
`App\Listeners\Notifications\BroadcastDatabaseNotificationCreated`).

- Channel: `private-user.{userId}`
- Broadcast timing: **after DB commit** — `ShouldBroadcastAfterCommit` so
  a rolled-back transaction never produces a ghost ping.
- Payload:

```json
{
  "id": "a9b1c0d2-3e4f-…",
  "type": "App\\Notifications\\Ads\\AdApprovedNotification",
  "data": {
    "category": "ad.approved",
    "title": "Your ad is live",
    "body": "Your ad \"iPhone 13\" has been approved and is now visible to buyers.",
    "cta_url": "https://qbazaar.qa/ads/01HM…",
    "icon": "badge-check",
    "ad_id": "01HM…"
  },
  "created_at": "2026-06-10T09:00:00+00:00"
}
```

`id` is the Laravel-generated UUID of the notifications row. The FE
should:

1. Increment its local unread-count or refetch
   `GET /api/v1/account/notifications/unread-count`.
2. Optionally prepend the notification to its in-memory list (the same
   payload shape is returned by `GET /api/v1/account/notifications`).
3. Animate the bell-icon indicator dot.

The `data` envelope is the verbatim payload `toArray()` returned on the
sender — the same JSON is persisted in the `notifications.data` column.
Unknown keys are forward-compatible: new notification classes may add
fields without breaking older clients.

## Categories shipped in Sprint 10

| `data.category` | Triggered by |
|------|------|
| `ad.approved`           | Ad clears moderation (auto or manual). |
| `ad.rejected`           | Auto-moderation flags an ad. |
| `ad.expiring_soon`      | Daily job 24h before `ads.expires_at`. |
| `ad.expired`            | Daily job after `ads.expires_at`. |
| `account.data_export_ready` | `ExportUserDataJob` finishes. |
| `security.new_device`   | Successful login from an unrecognised device. |

Future categories will land here as new notification classes are added.
