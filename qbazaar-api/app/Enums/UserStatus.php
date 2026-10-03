<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\Badgeable;

enum UserStatus: string implements Badgeable
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case DEACTIVATED = 'deactivated';
    case PENDING_DELETION = 'pending_deletion';

    /** Whether the user may sign in and use protected endpoints. */
    public function canLogin(): bool
    {
        return $this === self::ACTIVE;
    }

    /**
     * @return array{ar: string, en: string}
     */
    public function label(): array
    {
        return match ($this) {
            self::ACTIVE => ['ar' => 'نشط', 'en' => 'Active'],
            self::SUSPENDED => ['ar' => 'موقوف', 'en' => 'Suspended'],
            self::DEACTIVATED => ['ar' => 'معطّل', 'en' => 'Deactivated'],
            self::PENDING_DELETION => ['ar' => 'بانتظار الحذف', 'en' => 'Pending deletion'],
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::SUSPENDED => 'danger',
            self::DEACTIVATED, self::PENDING_DELETION => 'neutral',
        };
    }
}
