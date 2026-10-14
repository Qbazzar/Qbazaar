<?php

declare(strict_types=1);

namespace App\Services\Finance\Revenue;

use App\Enums\LedgerAccountType;
use App\Enums\LedgerReferenceType;
use App\Enums\OrderStatus;
use App\Support\Sql\LocalBucket;
use Generator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Every revenue query, each one a grouped aggregate (or a bounded page) over
 * ledger entries filtered by account and time. `ledger_entries` is read
 * through its (account_id, created_at) indexes, so the cost follows the rows
 * of the period, never the whole ledger, and no query runs per result row.
 */
final class RevenueRepository
{
    /**
     * The platform accounts a report reads, keyed by account type.
     *
     * @return array<string, object{id: string, type: string, balance: string}>
     */
    public function platformAccounts(): array
    {
        $types = [
            LedgerAccountType::PLATFORM_REVENUE,
            LedgerAccountType::PLATFORM_ESCROW,
            LedgerAccountType::PLATFORM_ADJUSTMENTS,
            LedgerAccountType::PLATFORM_PAYOUTS_PAYABLE,
        ];

        /** @var array<string, object{id: string, type: string, balance: string}> */
        return DB::table('ledger_accounts')
            ->whereIn('code', array_map(static fn (LedgerAccountType $type): string => $type->code(), $types))
            ->get(['id', 'type', 'balance'])
            ->keyBy('type')
            ->all();
    }

    /**
     * Debit and credit per account and transaction type, for the window and
     * the one before it, in a single pass.
     *
     * @param list<string> $accountIds
     * @return Collection<int, object>
     */
    public function movements(RevenueWindow $window, array $accountIds): Collection
    {
        $current = [$window->startUtc(), $window->endUtc()];
        $previous = [$window->previousStartUtc(), $window->previousEndUtc()];
        $sum = static fn (string $column, string $alias): string => "COALESCE(SUM(CASE WHEN e.created_at >= ? AND e.created_at < ? THEN {$column} ELSE 0 END), 0) as {$alias}";

        return DB::table('ledger_entries as e')
            ->join('ledger_transactions as t', 't.id', '=', 'e.transaction_id')
            ->whereIn('e.account_id', $accountIds)
            ->where(fn (Builder $range) => $range
                ->where(fn (Builder $q) => $q->where('e.created_at', '>=', $current[0])->where('e.created_at', '<', $current[1]))
                ->orWhere(fn (Builder $q) => $q->where('e.created_at', '>=', $previous[0])->where('e.created_at', '<', $previous[1])))
            ->selectRaw(
                'e.account_id, t.type, '
                . $sum('e.debit', 'current_debit') . ', ' . $sum('e.credit', 'current_credit') . ', '
                . $sum('e.debit', 'previous_debit') . ', ' . $sum('e.credit', 'previous_credit'),
                [...$current, ...$current, ...$previous, ...$previous],
            )
            ->groupBy('e.account_id', 't.type')
            ->get();
    }

    /**
     * Gross value and count of the orders completed in the window and the one before.
     *
     * @return object{current_gmv: string|null, current_orders: string|null, previous_gmv: string|null, previous_orders: string|null}
     */
    public function orderTotals(RevenueWindow $window): object
    {
        $current = [$window->startUtc(), $window->endUtc()];
        $previous = [$window->previousStartUtc(), $window->previousEndUtc()];
        $sum = static fn (string $value, string $alias): string => "COALESCE(SUM(CASE WHEN completed_at >= ? AND completed_at < ? THEN {$value} ELSE 0 END), 0) as {$alias}";

        return DB::table('orders')
            ->where('status', OrderStatus::COMPLETED->value)
            ->where(fn (Builder $range) => $range
                ->where(fn (Builder $q) => $q->where('completed_at', '>=', $current[0])->where('completed_at', '<', $current[1]))
                ->orWhere(fn (Builder $q) => $q->where('completed_at', '>=', $previous[0])->where('completed_at', '<', $previous[1])))
            ->selectRaw(
                $sum('total', 'current_gmv') . ', ' . $sum('1', 'current_orders') . ', ' . $sum('total', 'previous_gmv') . ', ' . $sum('1', 'previous_orders'),
                [...$current, ...$current, ...$previous, ...$previous],
            )
            ->first();
    }

    /**
     * Debit and credit per time bucket, account and transaction type, for the
     * window only.
     *
     * @param list<string> $accountIds
     * @return Collection<int, object>
     */
    public function series(RevenueWindow $window, array $accountIds): Collection
    {
        $bucket = LocalBucket::expression(DB::getDriverName(), 'e.created_at', $window->start->offsetHours, $window->bucket());

        return DB::table('ledger_entries as e')
            ->join('ledger_transactions as t', 't.id', '=', 'e.transaction_id')
            ->whereIn('e.account_id', $accountIds)
            ->where('e.created_at', '>=', $window->startUtc())
            ->where('e.created_at', '<', $window->endUtc())
            ->selectRaw("{$bucket} as bucket_start, e.account_id, t.type, SUM(e.debit) as debit, SUM(e.credit) as credit")
            ->groupBy('bucket_start', 'e.account_id', 't.type')
            ->get();
    }

    /**
     * Net platform income per category of the ad behind each order or promotion.
     *
     * @return Collection<int, object{category_key: string|null, income: string, line_count: int}>
     */
    public function incomeByCategory(RevenueWindow $window, string $revenueAccountId, int $limit): Collection
    {
        return $this->withReferences($this->revenueLines($window, $revenueAccountId))
            ->selectRaw('COALESCE(a1.category_id, a2.category_id) as category_key, SUM(e.credit) - SUM(e.debit) as income, COUNT(*) as line_count')
            ->groupBy('category_key')
            ->orderByDesc('income')
            ->limit($limit)
            ->get();
    }

    /**
     * Sellers ranked by the commission the platform charged on their orders.
     *
     * @return Collection<int, object{seller_id: string, commission: string, orders: int}>
     */
    public function topSellers(RevenueWindow $window, string $revenueAccountId, int $limit): Collection
    {
        return $this->withReferences($this->revenueLines($window, $revenueAccountId))
            ->whereIn('t.type', IncomeSource::COMMISSION->types())
            ->whereNotNull('o.seller_id')
            ->selectRaw('o.seller_id, SUM(e.credit) as commission, COUNT(*) as orders')
            ->groupBy('o.seller_id')
            ->orderByDesc('commission')
            ->limit($limit)
            ->get();
    }

    /**
     * One page of revenue lines, newest first, without counting the rest.
     */
    public function latest(RevenueWindow $window, string $revenueAccountId, int $perPage, int $page): Paginator
    {
        return $this->lineSelect($this->withReferences($this->revenueLines($window, $revenueAccountId)))
            ->orderByDesc('e.created_at')
            ->orderByDesc('e.id')
            ->simplePaginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Every revenue line of the window in chunks, oldest first. Each chunk
     * continues after the last (created_at, id) of the one before, which the
     * entries index serves, so no chunk re-reads or skips rows.
     *
     * @return Generator<int, list<object>>
     */
    public function chunks(RevenueWindow $window, string $revenueAccountId, int $size): Generator
    {
        $after = null;

        do {
            $query = $this->lineSelect($this->withReferences($this->revenueLines($window, $revenueAccountId)))
                ->orderBy('e.created_at')
                ->orderBy('e.id')
                ->limit($size);

            if ($after !== null) {
                $query->where(fn (Builder $q) => $q
                    ->where('e.created_at', '>', $after->created_at)
                    ->orWhere(fn (Builder $tie) => $tie->where('e.created_at', '=', $after->created_at)->where('e.id', '>', $after->id)));
            }

            $rows = $query->get()->all();

            if ($rows !== []) {
                yield $rows;
                $after = end($rows);
            }
        } while (count($rows) === $size);
    }

    /**
     * What the revenue account booked for one order, by transaction type.
     *
     * @return Collection<int, object{type: string, debit: string, credit: string}>
     */
    public function orderMovements(string $orderId): Collection
    {
        return DB::table('ledger_transactions as t')
            ->join('ledger_entries as e', 'e.transaction_id', '=', 't.id')
            ->join('ledger_accounts as a', 'a.id', '=', 'e.account_id')
            ->where('a.code', LedgerAccountType::PLATFORM_REVENUE->code())
            ->where('t.reference_type', LedgerReferenceType::ORDER->value)
            ->where('t.reference_id', $orderId)
            ->selectRaw('t.type, SUM(e.debit) as debit, SUM(e.credit) as credit')
            ->groupBy('t.type')
            ->get();
    }

    private function revenueLines(RevenueWindow $window, string $revenueAccountId): Builder
    {
        return DB::table('ledger_entries as e')
            ->join('ledger_transactions as t', 't.id', '=', 'e.transaction_id')
            ->where('e.account_id', $revenueAccountId)
            ->where('e.created_at', '>=', $window->startUtc())
            ->where('e.created_at', '<', $window->endUtc());
    }

    /**
     * Joins the order or the promotion a posting belongs to, and their ads.
     * All are primary-key lookups.
     */
    private function withReferences(Builder $query): Builder
    {
        return $query
            ->leftJoin('orders as o', fn (JoinClause $join) => $join->on('o.id', '=', 't.reference_id')->where('t.reference_type', '=', LedgerReferenceType::ORDER->value))
            ->leftJoin('ad_promotions as p', fn (JoinClause $join) => $join->on('p.id', '=', 't.reference_id')->where('t.reference_type', '=', LedgerReferenceType::PROMOTION->value))
            ->leftJoin('ads as a1', 'a1.id', '=', 'o.ad_id')
            ->leftJoin('ads as a2', 'a2.id', '=', 'p.ad_id');
    }

    private function lineSelect(Builder $query): Builder
    {
        return $query->selectRaw(
            'e.id, e.created_at, e.debit, e.credit, t.id as transaction_id, t.type, t.reference_type, t.reference_id, '
            . 'COALESCE(o.ad_id, p.ad_id) as ad_id, COALESCE(o.ad_title, a2.title) as ad_title, COALESCE(o.seller_id, p.user_id) as party_id',
        );
    }
}
