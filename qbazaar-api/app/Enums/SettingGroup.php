<?php

declare(strict_types=1);

namespace App\Enums;

enum SettingGroup: string
{
    case COMMISSION = 'commission';
    case ADS = 'ads';
    case OFFERS = 'offers';
    case PROMOTIONS = 'promotions';
}
