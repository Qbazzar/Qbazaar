# QBazaar — Notifications WebSocket Contract

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

## Categories in the code today

| `data.category` | Triggered by | FCM push |
|------|------|------|
| `ad.pending_review`     | A seller submits an ad; sent to staff with `super_admin` or `moderator` (database only). | — |
| `ad.approved`           | An admin approves the ad. | yes |
| `ad.rejected`           | An admin rejects the ad (auto-moderation only flags ads for the reviewer now). | yes |
| `ad.expiring_soon`      | Daily job, for ads expiring in the next 24 hours. | yes |
| `ad.expired`            | Daily job after `ads.expires_at`. | yes |
| `search.match`          | A new ad matches a saved search with alerts on. | yes |
| `account.data_export_ready` | `ExportUserDataJob` finishes. | yes |
| `security.new_device`   | Successful login from an unrecognised device. | — |
| `support.reply`         | Staff reply to the user's support ticket. | yes |
| `support.ticket_created` | A user opens a ticket; sent to staff. | — |
| `system.announcement`   | An admin broadcast. | yes |

FCM push only goes out when `FIREBASE_CREDENTIALS` is set and the user has a registered device token. Push for new messages and offers is not built yet (BE-14.2), and neither are `ads.new_from_followed` and `ad.price_changed` (BE-14.18).
