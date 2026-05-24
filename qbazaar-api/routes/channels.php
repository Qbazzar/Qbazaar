<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels — QBazaar
|--------------------------------------------------------------------------
|
| Channel authorization closures. Every callback returns either:
|   - false   — subscription denied (Reverb sends a 403 to the client).
|   - array   — subscription approved + presence info that is shipped to
|               every subscriber on the channel.
|
| The presence shape (`{id, name}`) is the minimum the chat UI needs to
| render typing indicators / "X is online" labels in Sprint 8 Wave B.
*/

// Laravel's default per-user broadcast channel, kept for Notification driver
// compatibility. New messaging code uses the explicit `user.{userId}` channel
// below.
Broadcast::channel('App.Models.User.{id}', function (User $user, string $id) {
    return $user->id === $id;
});

/*
 * conversation.{conversationId}
 *   Both participants subscribe; everyone else is refused. Returning the
 *   presence object lets us surface "the other side is reading" indicators
 *   in Wave B without a separate presence channel.
 */
Broadcast::channel('conversation.{conversationId}', function (User $user, string $conversationId) {
    /** @var Conversation|null $conversation */
    $conversation = Conversation::query()->find($conversationId);

    if ($conversation === null || ! $conversation->isParticipant($user)) {
        return false;
    }

    return ['id' => $user->id, 'name' => $user->full_name];
});

/*
 * user.{userId}
 *   Each user's personal channel — used for cross-conversation pings such
 *   as "new message in some other thread" (drives the header badge) and
 *   read-receipt fan-outs. Only the user themselves may subscribe.
 */
Broadcast::channel('user.{userId}', function (User $user, string $userId) {
    return $user->id === $userId;
});
