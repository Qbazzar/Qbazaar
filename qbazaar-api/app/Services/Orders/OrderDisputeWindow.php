<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PlatformSetting;
use App\Models\Order;
use App\Services\Payments\PaymentGateways;
use App\Services\Settings\SettingsService;
use Carbon\CarbonInterface;

/**
 * When a buyer may report a problem with an order, per the owner's rules:
 *
 *  - Cash: only the seller confirms the handover, which completes the
 *    order; the buyer then has the admin-set number of hours to report.
 *  - Escrow (bank transfer, card): the money stays held after the seller's
 *    handover until the buyer confirms or the admin-set number of days
 *    passes; the buyer can report until then.
 *
 * A problem can be reported once, and only after the handover.
 */
class OrderDisputeWindow
{
    public function __construct(
        private readonly PaymentGateways $gateways,
        private readonly SettingsService $settings,
    ) {}

    public function closesAt(Order $order): ?CarbonInterface
    {
        if ($order->handed_over_at === null || $order->disputed_at !== null) {
            return null;
        }

        if ($this->holdsEscrow($order->payment_method)) {
            return $order->status === OrderStatus::AWAITING_HANDOVER
                ? $order->handed_over_at->copy()->addDays($this->settings->integer(PlatformSetting::ESCROW_AUTO_RELEASE_DAYS))
                : null;
        }

        return $order->status === OrderStatus::COMPLETED
            ? $order->handed_over_at->copy()->addHours($this->settings->integer(PlatformSetting::ORDER_DISPUTE_WINDOW_HOURS))
            : null;
    }

    public function isOpen(Order $order): bool
    {
        return $this->closesAt($order)?->isFuture() === true;
    }

    /**
     * Escrow orders handed over before this moment are due for release.
     */
    public function releaseCutoff(): CarbonInterface
    {
        return now()->subDays($this->settings->integer(PlatformSetting::ESCROW_AUTO_RELEASE_DAYS));
    }

    /**
     * @return list<string>
     */
    public function escrowMethods(): array
    {
        return array_values(array_map(
            fn (PaymentMethod $method): string => $method->value,
            array_filter($this->gateways->methods(), fn (PaymentMethod $method): bool => $this->holdsEscrow($method)),
        ));
    }

    private function holdsEscrow(PaymentMethod $method): bool
    {
        return $this->gateways->for($method)->holdsFundsInEscrow();
    }
}
