<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\Badgeable;

enum LocationType: string implements Badgeable
{
    case CITY = 'city';
    case DISTRICT = 'district';
    case AREA = 'area';

    /**
     * @return array{ar: string, en: string}
     */
    public function label(): array
    {
        return match ($this) {
            self::CITY => ['ar' => 'مدينة', 'en' => 'City'],
            self::DISTRICT => ['ar' => 'منطقة', 'en' => 'District'],
            self::AREA => ['ar' => 'حي', 'en' => 'Area'],
        };
    }

    public function tone(): string
    {
        return 'neutral';
    }
}
