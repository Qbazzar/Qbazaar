<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Language scope on a moderation rule row.
 *
 *  - AR   — apply only when the ad text contains Arabic characters.
 *  - EN   — apply only when the ad text contains Latin characters.
 *  - ANY  — apply regardless (default; matches the legacy config-only behaviour).
 *
 * The language gate is opt-in. The service short-circuits to a plain match
 * when the rule language is `any`, so the new column adds no cost to the
 * legacy hot path.
 */
enum ModerationRuleLanguage: string
{
    case AR = 'ar';
    case EN = 'en';
    case ANY = 'any';
}
