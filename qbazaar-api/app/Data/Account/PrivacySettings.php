<?php

declare(strict_types=1);

namespace App\Data\Account;

use Spatie\LaravelData\Data;

/**
 * Privacy preferences a user can toggle on their account.
 *
 * Stored as a JSON column on `users.privacy_settings`. The User model casts the
 * column to this DTO, so business code reads typed booleans instead of an array
 * with missing keys. Defaults err on the user-friendly side — phone is visible
 * (matches the existing market norm in Qatari classifieds) but email is hidden
 * to keep direct outreach inside the platform.
 */
class PrivacySettings extends Data
{
    public function __construct(
        public bool $show_phone = true,
        public bool $show_email = false,
        public bool $allow_chat = true,
        public bool $indexed_by_search = true,
    ) {}

    /**
     * Convenience factory used by the User model cast when the DB value is null
     * (legacy rows that pre-date the `privacy_settings` migration default).
     */
    public static function defaults(): self
    {
        return new self;
    }
}
