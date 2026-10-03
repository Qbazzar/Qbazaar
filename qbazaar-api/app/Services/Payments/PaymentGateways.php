<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\PaymentMethod;
use LogicException;

/**
 * The gateways the platform offers, one per payment method. Bound in
 * AppServiceProvider; adding a gateway is one more entry there.
 */
final class PaymentGateways
{
    /** @var array<string, PaymentGateway> */
    private array $gateways = [];

    public function __construct(PaymentGateway ...$gateways)
    {
        foreach ($gateways as $gateway) {
            $this->gateways[$gateway->method()->value] = $gateway;
        }
    }

    public function for(PaymentMethod $method): PaymentGateway
    {
        return $this->gateways[$method->value]
            ?? throw new LogicException("No payment gateway is configured for [{$method->value}].");
    }

    public function default(): PaymentGateway
    {
        return $this->for(PaymentMethod::CASH);
    }

    /**
     * @return list<PaymentMethod>
     */
    public function methods(): array
    {
        return array_map(fn (string $method): PaymentMethod => PaymentMethod::from($method), array_keys($this->gateways));
    }
}
