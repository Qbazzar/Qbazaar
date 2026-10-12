<?php

declare(strict_types=1);

namespace App\Services\Admin\Dashboard\Collectors;

use App\Enums\LedgerAccountType;
use App\Enums\OrderStatus;
use App\Enums\PromotionStatus;
use App\Models\AdPromotion;
use App\Models\LedgerAccount;
use App\Models\Order;
use App\Services\Admin\Dashboard\DashboardWindow;
use App\Services\Admin\Dashboard\Trend;
use App\Support\Money;

/**
 * Orders, commission, wallets and promotion revenue. Amounts stay decimal
 * strings from the database to the view.
 */
final class FinanceStats
{
    /**
     * @param array{gmv_now: string, gmv_before: string, commission_now: string, commission_before: string, orders_now: int, orders_before: int} $completed
     * @return list<array<string, mixed>>
     */
    public function orderTiles(DashboardWindow $window, array $completed): array
    {
        $counts = $this->ordersByStatus($window);
        $sum = static fn (string $field): int => (int) collect($counts)->sum($field);

        $tiles = [
            ['key' => 'orders_total', 'kind' => 'count'] + Trend::between($sum('current_total'), $sum('previous_total')),
            ['key' => 'orders_gmv', 'kind' => 'money'] + Trend::between($completed['gmv_now'], $completed['gmv_before']),
        ];

        foreach (OrderStatus::cases() as $status) {
            $row = $counts[$status->value] ?? null;
            // Completed orders are counted when they complete, like GMV and commission.
            $tiles[] = ['key' => 'orders_' . $status->value, 'kind' => 'count'] + ($status === OrderStatus::COMPLETED
                ? Trend::between($completed['orders_now'], $completed['orders_before'])
                : Trend::between((int) ($row->current_total ?? 0), (int) ($row->previous_total ?? 0)));
        }

        return $tiles;
    }

    /**
     * @param array{gmv_now: string, gmv_before: string, commission_now: string, commission_before: string, orders_now: int, orders_before: int} $completed
     * @return list<array<string, mixed>>
     */
    public function commissionTiles(array $completed): array
    {
        $balances = $this->ledgerBalances();

        return [
            ['key' => 'commission_earned', 'kind' => 'money'] + Trend::between($completed['commission_now'], $completed['commission_before']),
            ['key' => 'commission_owed', 'kind' => 'money', 'value' => $balances[LedgerAccountType::USER_COMMISSION_RECEIVABLE->value] ?? Money::ZERO],
            ['key' => 'wallet_balances', 'kind' => 'money', 'value' => $balances[LedgerAccountType::USER_WALLET->value] ?? Money::ZERO],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function promotionTiles(DashboardWindow $window): array
    {
        $paid = [PromotionStatus::ACTIVE->value, PromotionStatus::EXPIRED->value];
        $revenue = $window->trend(AdPromotion::query()->whereIn('status', $paid), 'activated_at', 'price');

        return [
            ['key' => 'promotions_active', 'kind' => 'count', 'value' => AdPromotion::query()->where('status', PromotionStatus::ACTIVE->value)->count()],
            ['key' => 'promotions_revenue', 'kind' => 'money'] + $revenue,
        ];
    }

    /**
     * @return array<string, object>
     */
    private function ordersByStatus(DashboardWindow $window): array
    {
        [$sql, $bindings] = $window->selectSums('created_at');

        return Order::query()
            ->where('created_at', '>=', $window->previousStart)
            ->selectRaw('status, ' . $sql, $bindings)
            ->groupBy('status')
            ->toBase()
            ->get()
            ->keyBy('status')
            ->all();
    }

    /**
     * Completed orders only: GMV and commission are counted when the order
     * completes, which is when the ledger charges the commission.
     *
     * @return array{gmv_now: string, gmv_before: string, commission_now: string, commission_before: string, orders_now: int, orders_before: int}
     */
    public function completedTotals(DashboardWindow $window): array
    {
        $row = Order::query()
            ->where('status', OrderStatus::COMPLETED->value)
            ->where('completed_at', '>=', $window->previousStart)
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN completed_at >= ? THEN total ELSE 0 END), 0) as gmv_now, '
                . 'COALESCE(SUM(CASE WHEN completed_at < ? THEN total ELSE 0 END), 0) as gmv_before, '
                . 'COALESCE(SUM(CASE WHEN completed_at >= ? THEN commission_amount ELSE 0 END), 0) as commission_now, '
                . 'COALESCE(SUM(CASE WHEN completed_at < ? THEN commission_amount ELSE 0 END), 0) as commission_before, '
                . 'COALESCE(SUM(CASE WHEN completed_at >= ? THEN 1 ELSE 0 END), 0) as orders_now, '
                . 'COALESCE(SUM(CASE WHEN completed_at < ? THEN 1 ELSE 0 END), 0) as orders_before',
                [$window->start, $window->previousEnd, $window->start, $window->previousEnd, $window->start, $window->previousEnd],
            )
            ->toBase()
            ->get()->sole();

        return [
            'gmv_now' => Money::round((string) $row->gmv_now),
            'gmv_before' => Money::round((string) $row->gmv_before),
            'commission_now' => Money::round((string) $row->commission_now),
            'commission_before' => Money::round((string) $row->commission_before),
            'orders_now' => (int) $row->orders_now,
            'orders_before' => (int) $row->orders_before,
        ];
    }

    /**
     * @return array<string, string> account type => summed balance
     */
    private function ledgerBalances(): array
    {
        return LedgerAccount::query()
            ->whereIn('type', [LedgerAccountType::USER_COMMISSION_RECEIVABLE->value, LedgerAccountType::USER_WALLET->value])
            ->selectRaw('type, SUM(balance) as total')
            ->groupBy('type')
            ->toBase()
            ->pluck('total', 'type')
            ->map(static fn ($value): string => Money::round((string) $value))
            ->all();
    }
}
