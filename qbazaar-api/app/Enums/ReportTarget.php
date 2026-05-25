<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Resource types that can be reported via `POST /api/v1/reports`.
 *
 * MESSAGE was added in Sprint 10 alongside the abuse-report feature — sellers
 * receiving harassment in chat can now flag the offending bubble directly
 * instead of having to report the whole conversation.
 */
enum ReportTarget: string
{
    case AD = 'ad';
    case USER = 'user';
    case CONVERSATION = 'conversation';
    case MESSAGE = 'message';
}
