<?php

declare(strict_types=1);

namespace App\Enums;

enum OtpPurpose: string
{
    case PHONE_VERIFICATION = 'phone_verification';
}
