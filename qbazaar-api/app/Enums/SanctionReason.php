<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Preset reasons staff pick for a sanction (suspending or removing an ad,
 * suspending an account), a bulk rejection or a cancelled offer. The value is
 * stored and shown to the affected user in their language, so values are
 * stable once shipped; the free-text note beside it stays internal.
 */
enum SanctionReason: string
{
    case SPAM = 'spam';
    case PROHIBITED_ITEM = 'prohibited_item';
    case FRAUD = 'fraud';
    case MISLEADING = 'misleading';
    case OFFENSIVE = 'offensive';
    case HARASSMENT = 'harassment';
    case DUPLICATE = 'duplicate';
    case WRONG_CATEGORY = 'wrong_category';
    case OTHER = 'other';

    /** "Other" says nothing on its own, so staff must explain it. */
    public function requiresNote(): bool
    {
        return $this === self::OTHER;
    }

    /** Staff-facing label, in the admin panel's language. */
    public function adminLabel(?string $locale = null): string
    {
        return (string) __('admin.moderation.reasons.' . $this->value, [], $locale);
    }

    /** The wording the affected user reads, in their own language. */
    public function userLabel(string $locale): string
    {
        return (string) __('messages.moderation.reasons.' . $this->value, [], $locale);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
