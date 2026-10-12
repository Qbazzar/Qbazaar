<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\User;

/**
 * Tabs of the integrations section of /admin/settings. Contact data is public
 * and follows settings.manage; everything else holds credentials and is
 * limited to integrations.manage (super_admin).
 */
enum IntegrationGroup: string
{
    case CONTACT = 'contact';
    case EMAIL = 'email';
    case SMS = 'sms';
    case GOOGLE = 'google';
    case APPLE = 'apple';
    case PUSH = 'push';
    case ANTIBOT = 'antibot';
    case PAYMENT = 'payment';

    public function permission(): string
    {
        return $this === self::CONTACT ? 'settings.manage' : 'integrations.manage';
    }

    /**
     * @return list<self>
     */
    public static function visibleTo(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return array_values(array_filter(
            self::cases(),
            fn (self $group): bool => $user->can($group->permission()),
        ));
    }
}
