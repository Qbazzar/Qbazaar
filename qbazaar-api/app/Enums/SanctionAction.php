<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a sanction did. Each one names the permission it needs and the audit
 * event it writes, which match the routes that already did the same move
 * (so the entity timelines keep one title per action).
 */
enum SanctionAction: string
{
    case AD_SUSPENDED = 'ad_suspended';
    case AD_REMOVED = 'ad_removed';
    case USER_SUSPENDED = 'user_suspended';

    public const string SUBJECT_AD = 'ad';

    public const string SUBJECT_USER = 'user';

    public function permission(): string
    {
        return match ($this) {
            self::AD_SUSPENDED => 'ads.suspend',
            self::AD_REMOVED => 'ads.delete',
            self::USER_SUSPENDED => 'users.ban',
        };
    }

    public function auditEvent(): string
    {
        return match ($this) {
            self::AD_SUSPENDED => 'admin.ads.suspend',
            self::AD_REMOVED => 'admin.ads.destroy',
            self::USER_SUSPENDED => 'admin.users.suspend',
        };
    }

    public function subjectType(): string
    {
        return $this === self::USER_SUSPENDED ? self::SUBJECT_USER : self::SUBJECT_AD;
    }

    public function label(?string $locale = null): string
    {
        return (string) __('admin.moderation.actions.' . $this->value, [], $locale);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
