<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Events\Messaging\UnreadMessagesChanged;
use App\Models\User;
use App\Services\Messaging\ConversationInbox;

/**
 * Hides conversations from the caller's inbox only. The other participant
 * keeps seeing them, and the next message in a thread brings it back for
 * both sides. Ids that are not the caller's are ignored, so the response
 * never reveals whether someone else's conversation exists.
 */
class HideConversationsAction
{
    public function __construct(private readonly ConversationInbox $inbox) {}

    /**
     * @param list<string> $conversationIds
     * @return int number of conversations hidden
     */
    public function execute(User $user, array $conversationIds): int
    {
        $hidden = $this->inbox->hide($user, $conversationIds);

        // Unread messages in hidden threads leave the badge.
        if ($hidden > 0) {
            UnreadMessagesChanged::dispatch($user->id);
        }

        return $hidden;
    }
}
