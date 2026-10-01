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
    case COMMISSION_DEBT_CEILING = 'commission_debt_ceiling';
    case SETTLEMENT_DEADLINE_DAYS = 'settlement_deadline_days';
    case AD_EXPIRY_WARNING_DAYS = 'ad_expiry_warning_days';
    case OFFER_COUNTER_ROUNDS_PER_SIDE = 'offer_counter_rounds_per_side';

    public function definition(): SettingDefinition
    {
        return match ($this) {
            self::COMMISSION_DEBT_CEILING => SettingDefinition::decimal(SettingGroup::COMMISSION, 'qbazaar.commission.debt_ceiling', min: 0, max: 9_999_999_999.99),
            self::SETTLEMENT_DEADLINE_DAYS => SettingDefinition::integer(SettingGroup::COMMISSION, 'qbazaar.commission.settlement_deadline_days', min: 1, max: 90),
            self::AD_EXPIRY_WARNING_DAYS => SettingDefinition::integer(SettingGroup::ADS, 'qbazaar.ads.expiry_warning_days_before', min: 1, max: 30),
            self::OFFER_COUNTER_ROUNDS_PER_SIDE => SettingDefinition::integer(SettingGroup::OFFERS, 'qbazaar.offers.counter_rounds_per_side', min: 0, max: 5),
        };
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
