<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The paid promotions a seller can buy for an ad. Every active promotion
 * lifts the ad in search and on the home feed; the rank orders promoted ads
 * among themselves when one ad holds several.
 */
enum PromotionType: string
{
    case HIGHLIGHT = 'highlight';
    case PUSH_UP = 'push_up';
    case GALLERY = 'gallery';
    case PREMIUM = 'premium';

    public function rank(): int
    {
        return match ($this) {
            self::HIGHLIGHT => 1,
            self::PUSH_UP => 2,
            self::GALLERY => 3,
            self::PREMIUM => 4,
        };
    }

    public static function forRank(int $rank): ?self
    {
        foreach (self::cases() as $type) {
            if ($type->rank() === $rank) {
                return $type;
            }
        }

        return null;
    }

    public function priceSetting(): PlatformSetting
    {
        return match ($this) {
            self::HIGHLIGHT => PlatformSetting::PROMOTION_HIGHLIGHT_PRICE,
            self::PUSH_UP => PlatformSetting::PROMOTION_PUSH_UP_PRICE,
            self::GALLERY => PlatformSetting::PROMOTION_GALLERY_PRICE,
            self::PREMIUM => PlatformSetting::PROMOTION_PREMIUM_PRICE,
        };
    }

    public function durationSetting(): PlatformSetting
    {
        return match ($this) {
            self::HIGHLIGHT => PlatformSetting::PROMOTION_HIGHLIGHT_DAYS,
            self::PUSH_UP => PlatformSetting::PROMOTION_PUSH_UP_DAYS,
            self::GALLERY => PlatformSetting::PROMOTION_GALLERY_DAYS,
            self::PREMIUM => PlatformSetting::PROMOTION_PREMIUM_DAYS,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
