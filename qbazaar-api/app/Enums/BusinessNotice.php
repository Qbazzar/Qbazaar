<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a user is told about their business account. Each one is a
 * notification category and a key in lang/{locale}/business.php
 * `notifications`.
 */
enum BusinessNotice: string
{
    case APPLICATION_APPROVED = 'application_approved';
    case APPLICATION_REJECTED = 'application_rejected';
    case APPLICATION_NEEDS_INFO = 'application_needs_info';
    case VERIFICATION_GRANTED = 'verification_granted';
    case VERIFICATION_REVOKED = 'verification_revoked';

    public function category(): string
    {
        return 'business.' . $this->value;
    }
}
