<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Conversation;

/**
 * Admin transcripts open only after a stated reason (AD-17.11). Goes through
 * the real reason form so the grant lands in the test session the way it
 * does in the browser.
 */
trait OpensConversations
{
    protected function openConversation(Conversation $conversation, string $reason = 'Reviewing a report about this chat'): void
    {
        $this->post(route('admin.conversations.access', $conversation), ['reason' => $reason])
            ->assertRedirect(route('admin.conversations.show', $conversation));
    }
}
