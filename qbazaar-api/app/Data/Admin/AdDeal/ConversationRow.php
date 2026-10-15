<?php

declare(strict_types=1);

namespace App\Data\Admin\AdDeal;

use App\Models\Conversation;

/**
 * A conversation of the ad with its inbox state: the last message in the
 * viewer's language, unread messages across both sides and who hid it.
 */
final readonly class ConversationRow
{
    public function __construct(
        public Conversation $conversation,
        public ?string $excerpt,
        public int $unread,
        public bool $hiddenByBuyer,
        public bool $hiddenBySeller,
    ) {}
}
