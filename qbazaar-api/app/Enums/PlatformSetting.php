<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Settings\SettingDefinition;

/**
 * Registry of the admin-editable platform settings. Adding one is a new case
 * plus its definition line; storage, validation, the admin form and the
 * audit trail all derive from here.
 */
enum PlatformSetting: string
{
    case COMMISSION_RATE = 'commission_rate';
    case COMMISSION_DEBT_CEILING = 'commission_debt_ceiling';
    case SETTLEMENT_DEADLINE_DAYS = 'settlement_deadline_days';
    case AD_EXPIRY_WARNING_DAYS = 'ad_expiry_warning_days';
    case AD_MAX_IMAGES = 'ad_max_images';
    case AD_DAILY_PUBLISH_LIMIT = 'ad_daily_publish_limit';
    case OFFER_COUNTER_ROUNDS_PER_SIDE = 'offer_counter_rounds_per_side';
    case PROMOTION_HIGHLIGHT_PRICE = 'promotion_highlight_price';
    case PROMOTION_HIGHLIGHT_DAYS = 'promotion_highlight_days';
    case PROMOTION_PUSH_UP_PRICE = 'promotion_push_up_price';
    case PROMOTION_PUSH_UP_DAYS = 'promotion_push_up_days';
    case PROMOTION_GALLERY_PRICE = 'promotion_gallery_price';
    case PROMOTION_GALLERY_DAYS = 'promotion_gallery_days';
    case PROMOTION_PREMIUM_PRICE = 'promotion_premium_price';
    case PROMOTION_PREMIUM_DAYS = 'promotion_premium_days';
    case ORDER_DISPUTE_WINDOW_HOURS = 'order_dispute_window_hours';
    case ESCROW_AUTO_RELEASE_DAYS = 'escrow_auto_release_days';

    public function definition(): SettingDefinition
    {
        return match ($this) {
            self::COMMISSION_RATE => SettingDefinition::decimal(SettingGroup::COMMISSION, 'qbazaar.commission.rate', min: 0, max: 100),
            self::COMMISSION_DEBT_CEILING => SettingDefinition::decimal(SettingGroup::COMMISSION, 'qbazaar.commission.debt_ceiling', min: 0, max: 9_999_999_999.99),
            self::SETTLEMENT_DEADLINE_DAYS => SettingDefinition::integer(SettingGroup::COMMISSION, 'qbazaar.commission.settlement_deadline_days', min: 1, max: 90),
            self::AD_EXPIRY_WARNING_DAYS => SettingDefinition::integer(SettingGroup::ADS, 'qbazaar.ads.expiry_warning_days_before', min: 1, max: 30),
            self::AD_MAX_IMAGES => SettingDefinition::integer(SettingGroup::ADS, 'qbazaar.ads.max_images', min: 1, max: 20),
            self::AD_DAILY_PUBLISH_LIMIT => SettingDefinition::integer(SettingGroup::ADS, 'qbazaar.ads.daily_publish_limit_per_user', min: 1, max: 100),
            self::OFFER_COUNTER_ROUNDS_PER_SIDE => SettingDefinition::integer(SettingGroup::OFFERS, 'qbazaar.offers.counter_rounds_per_side', min: 0, max: 5),
            self::PROMOTION_HIGHLIGHT_PRICE => self::promotionPrice('highlight'),
            self::PROMOTION_HIGHLIGHT_DAYS => self::promotionDays('highlight'),
            self::PROMOTION_PUSH_UP_PRICE => self::promotionPrice('push_up'),
            self::PROMOTION_PUSH_UP_DAYS => self::promotionDays('push_up'),
            self::PROMOTION_GALLERY_PRICE => self::promotionPrice('gallery'),
            self::PROMOTION_GALLERY_DAYS => self::promotionDays('gallery'),
            self::PROMOTION_PREMIUM_PRICE => self::promotionPrice('premium'),
            self::PROMOTION_PREMIUM_DAYS => self::promotionDays('premium'),
            self::ORDER_DISPUTE_WINDOW_HOURS => SettingDefinition::integer(SettingGroup::ORDERS, 'qbazaar.orders.dispute_window_hours', min: 1, max: 720),
            self::ESCROW_AUTO_RELEASE_DAYS => SettingDefinition::integer(SettingGroup::ORDERS, 'qbazaar.orders.escrow_auto_release_days', min: 1, max: 60),
        };
    }

    private static function promotionPrice(string $type): SettingDefinition
    {
        return SettingDefinition::decimal(SettingGroup::PROMOTIONS, "qbazaar.promotions.types.{$type}.price", min: 1, max: 99_999.99);
    }

    private static function promotionDays(string $type): SettingDefinition
    {
        return SettingDefinition::integer(SettingGroup::PROMOTIONS, "qbazaar.promotions.types.{$type}.days", min: 1, max: 90);
    }

    /**
     * @return array<string, list<self>>
     */
    public static function grouped(): array
    {
        $grouped = [];

        foreach (self::cases() as $setting) {
            $grouped[$setting->definition()->group->value][] = $setting;
        }

        return $grouped;
    }
}
