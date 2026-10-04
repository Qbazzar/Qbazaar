<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How the seller wants the price treated.
 *
 *  - FIXED       — price stands as-is.
 *  - NEGOTIABLE  — buyers may submit offers below the listed price.
 *  - FREE        — giveaway; `price` MUST be NULL.
 *  - CONTACT     — "contact for price"; `price` MUST be NULL.
 *
 * The wire string ("contact") is locked in qbazaar-contracts; renaming
 * the case would break already-published OpenAPI clients.
 */
enum PriceType: string
{
    case FIXED = 'fixed';
    case NEGOTIABLE = 'negotiable';
    case FREE = 'free';
    case CONTACT = 'contact';

    /**
     * Price MUST be NULL for these — enforced in CreateAdRequest /
     * UpdateAdRequest. Centralised so the rule can't drift between
     * request validators and the model.
     */
    public function requiresNullPrice(): bool
    {
        return $this === self::FREE || $this === self::CONTACT;
    }

    /**
     * @return array{ar: string, en: string}
     */
    public function label(): array
    {
        return match ($this) {
            self::FIXED => ['ar' => 'سعر ثابت', 'en' => 'Fixed price'],
            self::NEGOTIABLE => ['ar' => 'قابل للتفاوض', 'en' => 'Negotiable'],
            self::FREE => ['ar' => 'مجاني', 'en' => 'Free'],
            self::CONTACT => ['ar' => 'بالتواصل', 'en' => 'Contact for price'],
        };
    }
}
