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
| `ad.expiring_soon`      | Hourly sweep, once per ad inside the `ad_expiry_warning_days` window. | yes |
| `ad.expired`            | Hourly sweep after `ads.expires_at`. | yes |
| `search.match`          | A new ad matches a saved search with alerts on. Every match reaches the bell; push goes out for the first match in each `qbazaar.search.saved_search_check_interval_minutes` window (60) per user. | first in window |
| `search.digest`         | Push only: the window closed with more matches (`data.matches`); they are already in the bell. | yes (push only) |
| `account.data_export_ready` | `ExportUserDataJob` finishes. | yes |
| `security.new_device`   | Successful login from an unrecognised device. | — |
| `support.reply`         | Staff reply to the user's support ticket. | yes |
| `support.ticket_created` | A user opens a ticket; sent to staff. | — |
| `system.announcement`   | An admin broadcast. | yes |
| `ad.price_changed`      | The seller lowers the price of a live ad; sent to everyone who favourited it (`data.previous_price`, `data.price`, `data.currency`). | yes |
| `ads.new_from_followed` | A followed seller's ad goes live (`data.seller_id`). Wired through `SellerFollowers`; silent until follows ship (BE-14.28). | yes |

FCM push only goes out when `FIREBASE_CREDENTIALS` is set and the user has a registered device token.

The two fan-out alerts run on the `low` queue in chunks of
`qbazaar.notifications.fan_out_chunk` users, skip the seller, inactive
accounts and blocks in either direction, and alert each user once per event
(`qbazaar.notifications.alert_dedupe_hours`).

`GET /account/notifications?category=` accepts a full category
(`ad.price_changed`) or a group prefix (`ad` matches every `ad.*`).

## Chat push (FCM only)

Chat has its own unread badges, so these categories are push-only: nothing is
stored in `notifications` and no `notification.created` is broadcast. They go
to the other participant, never to the person who acted, and only while the
recipient has no live Reverb connection (their `private-user.{id}` channel is
unoccupied). Set `CHAT_PUSH_SKIP_ONLINE=false` to push regardless of presence.

| `data.category` | Triggered by | Recipient |
|------|------|------|
| `message.new`     | A text message (`message.sent`); offer and system bubbles are skipped. `title` names the sender, `body` is the first 120 characters. | the other participant |
| `offer.created`   | `offer.created` | the seller |
| `offer.countered` | `offer.countered` | the side that made the countered offer |
| `offer.accepted`  | `offer.accepted` | the proposer |
| `offer.rejected`  | `offer.rejected` | the proposer |
| `offer.withdrawn` | `offer.withdrawn` | the responder |
| `offer.expired`   | `offer.expired` (timeout, ad sold or removed) | the buyer |

The FCM `data` block carries `title`, `body`, `category` and `cta_url`, which
deep-links to `/account/messages?c={conversationId}`.
