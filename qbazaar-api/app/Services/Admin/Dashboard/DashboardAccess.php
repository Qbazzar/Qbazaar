<?php

declare(strict_types=1);

namespace App\Services\Admin\Dashboard;

use App\Models\User;

/**
 * Which dashboard sections a staff member may see, derived from the same
 * permissions that guard the pages each section links to.
 */
final class DashboardAccess
{
    public const string CONTENT = 'content';

    public const string USERS = 'users';

    public const string REVIEW = 'review';

    public const string MODERATION = 'moderation';

    public const string CONVERSATIONS = 'conversations';

    public const string OFFERS = 'offers';

    public const string SUPPORT = 'support';

    public const string FINANCE = 'finance';

    public const string FINANCE_QUEUES = 'finance_queues';

    public const string SYSTEM = 'system';

    /** Section => the permission that unlocks it. */
    private const array PERMISSIONS = [
        self::CONTENT => 'ads.view',
        self::USERS => 'users.view',
        self::REVIEW => 'ads.approve',
        self::MODERATION => 'reports.view',
        self::CONVERSATIONS => 'conversations.view',
        self::OFFERS => 'offers.view',
        self::SUPPORT => 'support.view',
        self::FINANCE => 'finance.view',
        self::FINANCE_QUEUES => 'finance.manage',
        self::SYSTEM => 'settings.manage',
    ];

    /**
     * @return list<string> sorted, so it can double as a cache key
     */
    public static function sectionsFor(User $user): array
    {
        $sections = array_keys(array_filter(
            self::PERMISSIONS,
            static fn (string $permission): bool => $user->can($permission),
        ));
        sort($sections);

        return $sections;
    }
}
