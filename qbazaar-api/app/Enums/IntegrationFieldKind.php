<?php

declare(strict_types=1);

namespace App\Enums;

enum IntegrationFieldKind: string
{
    case TEXT = 'text';
    case EMAIL = 'email';
    case PHONE = 'phone';
    case URL = 'url';
    case TEXTAREA = 'textarea';
    case SELECT = 'select';
    case TOGGLE = 'toggle';
}
