<?php

declare(strict_types=1);

namespace App\Data\Admin\AdDeal;

use App\Models\Conversation;

/**
 * A conversation of the ad with its inbox state: the last message in the
 * viewer's language, unread messages across both sides and who hid it.
 * A user-written last message stays hidden (`privateExcerpt`) until the
 * transcript is opened with a stated reason.
 */
final readonly class ConversationRow
{
    public function __construct(
        public Conversation $conversation,
        public ?string $excerpt,
        public bool $privateExcerpt,
        public int $unread,
        public bool $hiddenByBuyer,
        public bool $hiddenBySeller,
    ) {}
}
