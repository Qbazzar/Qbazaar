<?php

declare(strict_types=1);

namespace App\Services\Finance\Revenue;

use App\Enums\LedgerAccountType;
use App\Models\Category;
use App\Models\User;
use App\Services\Admin\Dashboard\DashboardPeriod;
use App\Services\Catalog\WarmedCache;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Generator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Pagination\Paginator as SimplePaginator;
use Illuminate\Support\Collection;

/**
 * The platform's income statement, read from the ledger. Nothing here is
 * computed from ad prices: income is what the revenue account booked, refunds
 * are what it gave back, and the deductions come from the accounts that carry
 * them (see {@see IncomeFolder}).
 *
 * The aggregates are cached per range and per whether the viewer may see
 * sellers; one request rebuilds a cold entry while the others wait
 * ({@see WarmedCache}). The page of latest lines is read live and bounded.
 */
class RevenueReportService
{
    public const int TTL_SECONDS = 120;

    /** Newest lines browsable on screen; the CSV export holds the rest. */
    public const int MAX_PAGES = 10;

    public const int TOP_ROWS = 8;

    public const int EXPORT_CHUNK = 1000;

    private const string KEY_PREFIX = 'admin:revenue:v1:';

    public function __construct(
        private readonly WarmedCache $cache,
        private readonly RevenueRepository $repository,
        private readonly IncomeFolder $folder,
    ) {}

    public function report(RevenueWindow $window, bool $withSellers): RevenueReport
    {
        $key = self::KEY_PREFIX . $window->cacheKey() . ($withSellers ? ':sellers' : ':plain');

        return $this->cache->get($key, self::TTL_SECONDS, fn (): RevenueReport => $this->build($window, $withSellers));
    }

    /**
     * What the platform earned from one order. Reusable wherever an order is
     * shown (the ad deal panel), at the cost of a single indexed query.
     */
    public function platformIncomeForOrder(string $orderId): OrderPlatformIncome
    {
        $commission = $refunded = Money::ZERO;

        foreach ($this->repository->orderMovements($orderId) as $row) {
            $commission = Money::add($commission, Money::round((string) $row->credit));
            $refunded = Money::add($refunded, Money::round((string) $row->debit));
        }

        return new OrderPlatformIncome($orderId, $commission, $refunded);
    }

    /**
     * One page of the newest revenue lines of the window.
     *
     * @return Paginator<int, RevenueLine>
     */
    public function latestLines(RevenueWindow $window, ?string $revenueAccountId, int $page, int $perPage): Paginator
    {
        $page = max(1, min($page, self::MAX_PAGES));

        if ($revenueAccountId === null) {
            return new SimplePaginator([], $perPage, $page);
        }

        $paginator = $this->repository->latest($window, $revenueAccountId, $perPage, $page);
        $names = $this->userNames($paginator->getCollection()->pluck('party_id'));

        // Capped here, not in the query: the last allowed page never offers a next one.
        if ($page >= self::MAX_PAGES) {
            $paginator->hasMorePagesWhen(false);
        }

        return $paginator->through(fn (object $row): RevenueLine => $this->line($row, $names));
    }

    /**
     * Every revenue line of the window, in chunks, for the CSV export.
     *
     * @return Generator<int, list<RevenueLine>>
     */
    public function exportLines(RevenueWindow $window, ?string $revenueAccountId): Generator
    {
        if ($revenueAccountId === null) {
            return;
        }

        foreach ($this->repository->chunks($window, $revenueAccountId, self::EXPORT_CHUNK) as $rows) {
            yield array_map(fn (object $row): RevenueLine => $this->line($row, new Collection), $rows);
        }
    }

    public function revenueAccountId(): ?string
    {
        return ($this->repository->platformAccounts()[LedgerAccountType::PLATFORM_REVENUE->value] ?? null)?->id;
    }

    private function build(RevenueWindow $window, bool $withSellers): RevenueReport
    {
        $accounts = $this->repository->platformAccounts();
        $ids = array_values(array_map(static fn (object $account): string => $account->id, $accounts));
        $types = array_column(array_map(static fn (object $account): array => [$account->id, $account->type], $accounts), 1, 0);
        $revenueId = ($accounts[LedgerAccountType::PLATFORM_REVENUE->value] ?? null)?->id;
        $now = CarbonImmutable::now(DashboardPeriod::TIMEZONE);

        $movements = $ids === [] ? new Collection : $this->repository->movements($window, $ids);
        $orders = $this->repository->orderTotals($window);

        return new RevenueReport(
            window: $window,
            current: $this->folder->fold(IncomeFolder::period($movements, 'current'), $types, $this->money($orders->current_gmv), (int) $orders->current_orders),
            previous: $this->folder->fold(IncomeFolder::period($movements, 'previous'), $types, $this->money($orders->previous_gmv), (int) $orders->previous_orders),
            escrowHeld: $this->balance($accounts, LedgerAccountType::PLATFORM_ESCROW),
            payoutsPending: $this->balance($accounts, LedgerAccountType::PLATFORM_PAYOUTS_PAYABLE),
            series: $ids === [] ? [] : $this->series($window, $ids, $types),
            categories: $revenueId === null ? [] : $this->categories($window, $revenueId),
            sellers: $withSellers && $revenueId !== null ? $this->sellers($window, $revenueId) : ($withSellers ? [] : null),
            generatedAt: $now,
            revenueAccountId: $revenueId,
        );
    }

    /**
     * @param list<string> $ids
     * @param array<string, string> $types
     * @return list<array{date: string, income: string, deductions: string, net: string}>
     */
    private function series(RevenueWindow $window, array $ids, array $types): array
    {
        $buckets = $this->repository->series($window, $ids)->groupBy('bucket_start');

        return array_map(function (string $date) use ($buckets, $types): array {
            $statement = $this->folder->fold($buckets->get($date, []), $types, Money::ZERO, 0);

            return [
                'date' => $date,
                'income' => $statement->totalIncome(),
                'deductions' => $statement->totalDeductions(),
                'net' => $statement->netProfit(),
            ];
        }, $window->bucketStarts());
    }

    /**
     * @return list<array{id: string|null, name: array<string, string>|null, income: string, lines: int}>
     */
    private function categories(RevenueWindow $window, string $revenueId): array
    {
        $rows = $this->repository->incomeByCategory($window, $revenueId, self::TOP_ROWS);
        $names = Category::query()->whereIn('id', $rows->pluck('category_key')->filter())->pluck('name', 'id');

        return $rows->map(fn (object $row): array => [
            'id' => $row->category_key,
            'name' => $row->category_key === null ? null : $this->localizedNames($names->get($row->category_key)),
            'income' => Money::round((string) $row->income),
            'lines' => (int) $row->line_count,
        ])->all();
    }

    /**
     * @return list<array{id: string, name: string, commission: string, orders: int}>
     */
    private function sellers(RevenueWindow $window, string $revenueId): array
    {
        $rows = $this->repository->topSellers($window, $revenueId, self::TOP_ROWS);
        $names = $this->userNames($rows->pluck('seller_id'));

        return $rows->map(fn (object $row): array => [
            'id' => (string) $row->seller_id,
            'name' => $names->get($row->seller_id, ''),
            'commission' => Money::round((string) $row->commission),
            'orders' => (int) $row->orders,
        ])->all();
    }

    /**
     * @param Collection<int, mixed> $ids
     * @return Collection<string, string> display name by user id
     */
    private function userNames(Collection $ids): Collection
    {
        $ids = $ids->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return new Collection;
        }

        return User::query()->whereIn('id', $ids)->pluck('full_name', 'id');
    }

    /**
     * @param Collection<string, string> $names
     */
    private function line(object $row, Collection $names): RevenueLine
    {
        return new RevenueLine(
            id: (string) $row->id,
            at: CarbonImmutable::parse((string) $row->created_at, 'UTC')->setTimezone(DashboardPeriod::TIMEZONE),
            type: (string) $row->type,
            credit: Money::round((string) $row->credit),
            debit: Money::round((string) $row->debit),
            referenceType: $row->reference_type,
            referenceId: $row->reference_id,
            adId: $row->ad_id,
            adTitle: $row->ad_title,
            partyId: $row->party_id,
            partyName: $row->party_id === null ? null : $names->get($row->party_id),
        );
    }

    /**
     * @param array<string, object> $accounts
     */
    private function balance(array $accounts, LedgerAccountType $type): string
    {
        $account = $accounts[$type->value] ?? null;

        return $account === null ? Money::ZERO : Money::round((string) $account->balance);
    }

    private function money(mixed $value): string
    {
        return Money::round((string) ($value ?? 0));
    }

    /**
     * @return array<string, string>|null
     */
    private function localizedNames(mixed $names): ?array
    {
        $names = is_string($names) ? json_decode($names, true) : $names;

        return is_array($names) ? $names : null;
    }
}
