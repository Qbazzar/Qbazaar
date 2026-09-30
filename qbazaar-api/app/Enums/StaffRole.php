<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\User;

enum StaffRole: string
{
    case SUPER_ADMIN = 'super_admin';
    case MODERATOR = 'moderator';
    case SUPPORT = 'support';

    /** @return list<string> */
    public static function names(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function rank(): int
    {
        return match ($this) {
            self::SUPER_ADMIN => 3,
            self::MODERATOR => 2,
            self::SUPPORT => 1,
        };
    }

    /** Highest staff rank the user holds; 0 for non-staff. */
    public static function rankOf(User $user): int
    {
        return $user->getRoleNames()
            ->map(fn (string $name): int => self::tryFrom($name)?->rank() ?? 0)
            ->max() ?? 0;
    }
}
