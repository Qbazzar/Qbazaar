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

    /**
     * @return array{ar: string, en: string}
     */
    public function label(): array
    {
        return match ($this) {
            self::ANY => ['ar' => 'الكل', 'en' => 'Any'],
            self::AR => ['ar' => 'العربية', 'en' => 'Arabic'],
            self::EN => ['ar' => 'الإنجليزية', 'en' => 'English'],
        };
    }
}
