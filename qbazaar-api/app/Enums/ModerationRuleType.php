<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\Badgeable;

/**
 * Auto-moderation rule families exposed by the admin DB editor.
 *
 *  - BANNED_WORD     — substring match against title + description.
 *  - BLOCKED_DOMAIN  — host name that must NOT appear in any URL of the ad
 *                      (complements the allowed-domains allow-list in
 *                      config/moderation.php — both are consulted).
 */
enum ModerationRuleType: string implements Badgeable
{
    case BANNED_WORD = 'banned_word';
    case BLOCKED_DOMAIN = 'blocked_domain';

    /**
     * @return array{ar: string, en: string}
     */
    public function label(): array
    {
        return match ($this) {
            self::BANNED_WORD => ['ar' => 'كلمة محظورة', 'en' => 'Banned word'],
            self::BLOCKED_DOMAIN => ['ar' => 'نطاق محظور', 'en' => 'Blocked domain'],
        };
    }

    public function tone(): string
    {
        return 'warning';
    }
}
