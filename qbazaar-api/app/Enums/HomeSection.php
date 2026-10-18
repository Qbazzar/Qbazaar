<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The home screen rails staff can curate by hand. Each one shows at most
 * its configured number of ads, curated or automatic.
 */
enum HomeSection: string
{
    case RECOMMENDED = 'recommended';
    case BEST_SELLING = 'best_selling';

    public function limit(): int
    {
        return (int) config(match ($this) {
            self::RECOMMENDED => 'qbazaar.home.recommended_limit',
            self::BEST_SELLING => 'qbazaar.home.best_selling_limit',
        });
    }

    /**
     * @return array{ar: string, en: string}
     */
    public function label(): array
    {
        return match ($this) {
            self::RECOMMENDED => ['ar' => 'المقترحة', 'en' => 'Recommended'],
            self::BEST_SELLING => ['ar' => 'الأكثر مبيعاً', 'en' => 'Best selling'],
        };
    }
}
