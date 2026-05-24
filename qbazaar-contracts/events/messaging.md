# QBazaar — Messaging WebSocket Contract (Sprint 8 Wave A)

Broadcast adapter: **Laravel Reverb** (Pusher-compatible).
Auth: clients call `POST /broadcasting/auth` with their Sanctum bearer.

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

## Client behaviour notes

- WebSocket delivery is best-effort. On reconnect, fetch missed traffic
  via REST (`GET /conversations/{id}/messages`) — see
  `events/messages.yaml` reconnection block.
- The frontend Echo client should treat `message.sent` arriving on the
  user channel and on the conversation channel as **the same event** —
  dedupe by `message.id`. The two delivery paths exist so the badge
  updates even when the conversation pane is closed.
