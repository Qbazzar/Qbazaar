# QBazaar — Messaging WebSocket Contract

Broadcast adapter: **Laravel Reverb** (Pusher-compatible).
Auth: clients call `POST /api/v1/broadcasting/auth` with their Sanctum bearer (`Authorization: Bearer <token>`). The response is the raw Pusher `{auth}` body, not the API envelope.

## Channels

| Pattern | Visibility | Subscribers | Purpose |
| --- | --- | --- | --- |
| `private-conversation.{conversationId}` | private | Both conversation participants | Chat-screen events: new messages |
| `private-user.{userId}` | private | The user themselves | Cross-conversation pings: unread badge, read receipts |

Both channels are wired in `routes/channels.php`:

```php
Broadcast::channel('conversation.{conversationId}', function (User $user, string $conversationId) {
    $conv = Conversation::find($conversationId);
    return $conv && $conv->isParticipant($user)
        ? ['id' => $user->id, 'name' => $user->full_name]
        : false;
});

Broadcast::channel('user.{userId}', function (User $user, string $userId) {
    return $user->id === $userId;
});
```

The conversation channel returns a presence-style payload so Wave B
typing-indicator + "X is online" labels can build on top without a
schema change.

## Events

### `message.sent`

Fired when `POST /conversations/{id}/messages` succeeds (after DB commit).

- Channels:
  - `private-conversation.{conversationId}` — for clients with the thread open.
  - `private-user.{recipientId}` — for the recipient's header badge / inbox row.
- Payload:

```json
{
  "message": {
    "id": "01HMxx...",
    "conversation_id": "01HMxx...",
    "sender_id": "01HMxx...",
    "body": "Hello! Is this still available?",
    "type": "text",
    "read_at": null,
    "created_at": "2026-05-24T09:00:00+00:00",
    "sender": {
      "id": "01HMxx...",
      "full_name": "Ali",
      "avatar_thumb": "https://.../thumb.jpg"
    }
  },
  "conversation": {
    "id": "01HMxx...",
    "last_message_preview": "Hello! Is this still available?",
    "last_message_at": "2026-05-24T09:00:00+00:00",
    "unread_count": 3
  }
}
```

`conversation.unread_count` is computed relative to the recipient, NOT
the sender. The same event uses two channels because the
conversation-channel `unread_count` would be ambiguous when both
participants are listening — but we keep the recipient-specific value
correct on the recipient's `user.*` channel.

### `conversation.read`

Fired when `POST /conversations/{id}/read` actually marks anything
(no-op reads do not broadcast).

- Channels:
  - `private-user.{otherUserId}` — the sender of the messages that were just read.
- Payload:

```json
{
  "conversation_id": "01HMxx...",
  "reader_id": "01HMxx...",
  "read_at": "2026-05-24T09:05:00+00:00"
}
```

Clients should update their per-bubble "delivered" → "read" markers for
every message in this conversation older than `read_at`.

### `offer.created`

Fired when `POST /conversations/{id}/offers` succeeds (Sprint 9).

- Channels:
  - `private-conversation.{conversationId}` — both participants flip the chat to show the inline offer card.
  - `private-user.{otherUserId}` — the seller (recipient) updates their inbox row + header badge when the thread isn't open.
- Payload:

```json
{
  "offer": {
    "id": "01HMxx...",
    "conversation_id": "01HMxx...",
    "ad_id": "01HMxx...",
    "buyer_id": "01HMxx...",
    "seller_id": "01HMxx...",
    "proposed_by": "buyer",
    "parent_offer_id": null,
    "counter_round": 0,
    "message_id": "01HMxx...",
    "amount": "1500.00",
    "currency": "QAR",
    "note": "Can we meet halfway?",
    "status": "pending",
    "expires_at": "2026-06-01T00:00:00+00:00",
    "accepted_at": null,
    "rejected_at": null,
    "withdrawn_at": null,
    "countered_at": null,
    "created_at": "2026-05-25T09:00:00+00:00",
    "viewer_role": null
  },
  "conversation_id": "01HMxx..."
}
```

`viewer_role` is `null` on the broadcast path — clients re-derive it
from their own session (`buyer` if user.id === offer.buyer_id, otherwise
`seller`).

The `private-user` channel of every offer event below belongs to the other
side of the action: the proposer for accept/reject, the responder for
withdraw and counter, and the buyer for expiry.

### `offer.accepted`

Fired after `POST /offers/{id}/accept` commits.

- Channels: `private-conversation.{conversationId}` + `private-user.{proposerId}`.
- Payload: identical shape to `offer.created`; `status` flips to `accepted`,
  `accepted_at` populated.

### `offer.rejected`

Fired after `POST /offers/{id}/reject` commits.

- Channels: `private-conversation.{conversationId}` + `private-user.{proposerId}`.
- Payload: `status` = `rejected`, `rejected_at` populated.

### `offer.withdrawn`

Fired after `POST /offers/{id}/withdraw` commits.

- Channels: `private-conversation.{conversationId}` + `private-user.{responderId}`.
- Payload: `status` = `withdrawn`, `withdrawn_at` populated.

### `offer.expired`

Server-side only. Dispatched by `ExpireOldOffersJob` (daily 02:30 Asia/Qatar)
when a pending offer's `expires_at` has passed, and whenever the platform
closes an open offer: the ad was sold, expired, blocked or deleted, or another
offer on the same ad was accepted.

- Channels: `private-conversation.{conversationId}` + `private-user.{buyerId}`.
- Payload: `status` = `expired`.

### `offer.countered`

Fired after `POST /offers/{id}/counter` commits.

- Channels: `private-conversation.{conversationId}` + `private-user.{proposerId of the answered offer}`.
- Payload: the NEW counter-offer (`status` = `pending`, `proposed_by` = the
  countering side, `parent_offer_id` = the answered offer, `counter_round`
  one higher). The answered offer is now `countered`; refetch it or mark it
  locally from `parent_offer_id`.

## Client events (whispers)

### `typing`

Sent by a client, not the server: `channel.whisper('typing', { user_id })` on
`private-conversation.{conversationId}`. Reverb relays it to the other
participant without touching the API. The web client throttles outgoing
whispers to one every 2 seconds and hides the indicator 3 seconds after the
last one (`qbazaar-web/lib/echo/useTypingIndicator.ts`).

## Client behaviour notes

- WebSocket delivery is best-effort. On reconnect, fetch missed traffic
  via REST (`GET /conversations/{id}/messages`) — see
  `events/messages.yaml` reconnection block.
- The frontend Echo client should treat `message.sent` arriving on the
  user channel and on the conversation channel as **the same event** —
  dedupe by `message.id`. The two delivery paths exist so the badge
  updates even when the conversation pane is closed.
