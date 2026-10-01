<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Hides conversations from the caller's inbox only. The other participant
 * keeps seeing them, and the next message in a thread brings it back for
 * both sides. Ids that are not the caller's are ignored, so the response
 * never reveals whether someone else's conversation exists.
 */
class HideConversationsAction
{
    /**
     * @param list<string> $conversationIds
     * @return int number of conversations hidden
     */
    public function execute(User $user, array $conversationIds): int
    {
        return DB::transaction(fn (): int => $this->hideAs('buyer', $user, $conversationIds)
            + $this->hideAs('seller', $user, $conversationIds));
    }

    /**
     * @param 'buyer'|'seller' $side
     * @param list<string> $conversationIds
     */
    private function hideAs(string $side, User $user, array $conversationIds): int
    {
        return Conversation::query()
            ->whereKey($conversationIds)
            ->where("{$side}_id", $user->id)
            ->update(["{$side}_hidden_at" => now()]);
    }
}
