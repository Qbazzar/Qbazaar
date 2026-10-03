<?php

declare(strict_types=1);

namespace App\Services\Promotions;

use App\Data\Promotions\PromotionOffer;
use App\Enums\PromotionType;
use App\Services\Settings\SettingsService;

/**
 * The promotion types on sale with their admin-set price and duration.
 * One price and one duration per type keeps them ordinary platform
 * settings: the admin form, validation, cache and audit trail come for free.
 */
class PromotionCatalog
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    /**
     * @return list<PromotionOffer>
     */
    public function all(): array
    {
        return array_map($this->offerFor(...), PromotionType::cases());
    }

    public function offerFor(PromotionType $type): PromotionOffer
    {
        return new PromotionOffer(
            $type,
            $this->settings->decimal($type->priceSetting()),
            (string) config('qbazaar.default_currency', 'QAR'),
            $this->settings->integer($type->durationSetting()),
        );
    }
}
