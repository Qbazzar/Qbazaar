<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

/**
 * Authorization rules for the chat subsystem.
 *
 * The richer rules (own-ad refusal, block detection) live in the
 * Messaging actions so they can surface their dedicated ErrorCodes
 * (`MSG_CONVERSATION_OWN_AD`, `MSG_BLOCKED`). The policy here only
 * enforces "the caller is one of the two participants" — the universal
 * predicate for every conversation-scoped endpoint.
 *
 * Returning false here lands the caller in the generic 403 FORBIDDEN
 * envelope (via the AuthorizationException renderer). That's the right
 * behaviour for "you're peeking at someone else's chat".
 */
class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->isParticipant($user);
    }

    /**
     * Send-permission. Block detection is intentionally NOT here — the
     * SendMessageAction re-checks live so the failure surfaces as the
     * stable MSG_BLOCKED domain error instead of a generic FORBIDDEN.
     */
    public function send(User $user, Conversation $conversation): bool
    {
        return $conversation->isParticipant($user);
    }

    public function markRead(User $user, Conversation $conversation): bool
    {
        return $conversation->isParticipant($user);
    }
}
