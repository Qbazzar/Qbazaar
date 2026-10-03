<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How the buyer pays. Each method has one PaymentGateway implementation;
 * only cash exists until an electronic gateway is contracted (M7).
 */
enum PaymentMethod: string
{
    case CASH = 'cash';
}
