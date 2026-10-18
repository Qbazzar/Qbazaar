<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\Badgeable;

/**
 * Automatic sections follow the built-in ranking rule; manual ones show the
 * ads staff picked, in their order.
 */
enum HomeSectionMode: string implements Badgeable
{
    case AUTO = 'auto';
    case MANUAL = 'manual';

    /**
     * @return array{ar: string, en: string}
     */
    public function label(): array
    {
        return match ($this) {
            self::AUTO => ['ar' => 'تلقائي', 'en' => 'Automatic'],
            self::MANUAL => ['ar' => 'يدوي', 'en' => 'Manual'],
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::AUTO => 'neutral',
            self::MANUAL => 'brand',
        };
    }
}
