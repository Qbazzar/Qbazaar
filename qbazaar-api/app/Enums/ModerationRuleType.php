<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Auto-moderation rule families exposed by the admin DB editor.
 *
 *  - BANNED_WORD     — substring match against title + description.
 *  - BLOCKED_DOMAIN  — host name that must NOT appear in any URL of the ad
 *                      (complements the allowed-domains allow-list in
 *                      config/moderation.php — both are consulted).
 */
enum ModerationRuleType: string
{
    case BANNED_WORD = 'banned_word';
    case BLOCKED_DOMAIN = 'blocked_domain';
}
