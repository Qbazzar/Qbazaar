<?php

declare(strict_types=1);

namespace App\Data\Admin\AdDeal;

use App\Models\User;

/**
 * Which parts of a deal the signed-in staff member may read, each by the
 * permission that already guards that data elsewhere in the panel.
 */
final readonly class DealAccess
{
    public function __construct(
        public bool $finance,
        public bool $conversations,
        public bool $offers,
        public bool $users,
    ) {}

    public static function of(User $viewer): self
    {
        return new self(
            finance: $viewer->can('finance.view'),
            conversations: $viewer->can('conversations.view'),
            offers: $viewer->can('offers.view'),
            users: $viewer->can('users.view'),
        );
    }
}
