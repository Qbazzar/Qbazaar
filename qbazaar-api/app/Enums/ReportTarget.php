<?php

declare(strict_types=1);

namespace App\Enums;

enum ReportTarget: string
{
    case AD = 'ad';
    case USER = 'user';
    case CONVERSATION = 'conversation';
}
