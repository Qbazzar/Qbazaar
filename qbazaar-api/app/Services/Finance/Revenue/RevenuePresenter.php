<?php

declare(strict_types=1);

namespace App\Services\Finance\Revenue;

use App\Models\User;
use App\Services\Admin\Dashboard\Trend;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Pagination\Paginator;

/**
 * Turns a {@see RevenueReport} into what the Blade page prints: formatted
 * figures, labelled statement rows, chart series and table rows with the
 * links the viewer may follow. Pure presentation, so the view holds no logic.
 */
final class RevenuePresenter
{
    private const array SOURCE_TONES = ['commission' => 'coral', 'promotions' => 'violet', 'subscriptions' => 'aqua', 'other' => 'slate'];

    /**
     * @param Paginator<int, RevenueLine> $lines
     */
    public function __construct(
        private readonly RevenueReport $report,
        private readonly Paginator $lines,
        private readonly User $viewer,
    ) {}

    public function window(): RevenueWindow
    {
        return $this->report->window;
    }

    public function generatedAt(): string
    {
        return $this->report->generatedAt->diffForHumans();
    }

    public function currency(): string
    {
        return (string) config('qbazaar.default_currency', 'QAR');
    }

    public function money(string $amount): string
    {
        return Money::format($amount) . ' ' . $this->currency();
    }

    /**
     * @return list<array{key: string, label: string, value: string, change: int|null|false, caption: string, icon: string}>
     */
    public function kpis(): array
    {
        $now = $this->report->current;
        $before = $this->report->previous;

        return [
            $this->kpi('gmv', $now->gmv, $before->gmv, 'banknotes'),
            $this->kpi('income', $now->totalIncome(), $before->totalIncome(), 'trend'),
            // More deductions is not good news, so no green arrow: the earlier figure is printed instead.
            $this->kpi('deductions', $now->totalDeductions(), $before->totalDeductions(), 'adjustments', trend: false),
            $this->kpi('net', $now->netProfit(), $before->netProfit(), 'banknotes'),
            $this->kpi('payouts', $now->payoutsPaid, $before->payoutsPaid, 'users'),
            [
                'key' => 'escrow',
                'label' => __('admin.revenue.kpis.escrow'),
                'value' => $this->money($this->report->escrowHeld),
                'change' => false,
                'caption' => __('admin.revenue.kpis.escrow_note'),
                'icon' => 'shield',
            ],
        ];
    }

    /**
     * The income statement, line by line. A null amount is a line the ledger
     * does not record yet; the view prints its note instead of a number.
     *
     * @return list<array{key: string, label: string, kind: string, value: string|null, previous: string|null, change: int|null|false, note: string|null}>
     */
    public function statement(): array
    {
        $now = $this->report->current;
        $before = $this->report->previous;
        $rows = [$this->heading('income')];

        foreach (IncomeSource::cases() as $source) {
            $rows[] = $this->line($source->value, $now->income[$source->value], $before->income[$source->value]);
        }

        $rows[] = $this->line('total_income', $now->totalIncome(), $before->totalIncome(), 'subtotal');
        $rows[] = $this->heading('deductions');
        $rows[] = $this->line('refunds', $now->refunds, $before->refunds);
        $rows[] = $this->line('gateway_fees', $now->gatewayFees, $before->gatewayFees);
        $rows[] = $this->line('absorbed', $now->absorbed, $before->absorbed);
        $rows[] = $this->line('total_deductions', $now->totalDeductions(), $before->totalDeductions(), 'subtotal');
        $rows[] = $this->line('net', $now->netProfit(), $before->netProfit(), 'total');

        return $rows;
    }

    /**
     * Money held for others and not yet paid out: liabilities, not income.
     *
     * @return list<array{label: string, value: string}>
     */
    public function liabilities(): array
    {
        return [
            ['label' => __('admin.revenue.liabilities.escrow'), 'value' => $this->money($this->report->escrowHeld)],
            ['label' => __('admin.revenue.liabilities.payouts_pending'), 'value' => $this->money($this->report->payoutsPending)],
        ];
    }

    /**
     * Income and net profit per bucket for the lines chart. The chart axis
     * starts at zero, so a losing bucket is drawn at zero; {@see hasLoss()}
     * tells the view to say so.
     *
     * @return list<array{label: string, tone: string, field: string, points: list<array<string, string>>}>
     */
    public function trendSeries(): array
    {
        $income = $net = [];

        foreach ($this->report->series as $point) {
            $income[] = ['date' => $point['date'], 'income' => $point['income']];
            $net[] = ['date' => $point['date'], 'net' => Money::isNegative($point['net']) ? Money::ZERO : $point['net']];
        }

        return [
            ['label' => __('admin.revenue.charts.income'), 'tone' => 'blue', 'field' => 'income', 'points' => $income],
            ['label' => __('admin.revenue.charts.net'), 'tone' => 'success', 'field' => 'net', 'points' => $net],
        ];
    }

    public function hasLoss(): bool
    {
        return array_filter($this->report->series, static fn (array $point): bool => Money::isNegative($point['net'])) !== [];
    }

    public function bucketLabel(): string
    {
        return __('admin.revenue.charts.bucket_' . $this->report->window->bucket());
    }

    /**
     * @return list<array{label: string, value: float, tone: string}>
     */
    public function sourceSlices(): array
    {
        $slices = [];

        foreach (IncomeSource::cases() as $source) {
            $amount = $this->report->current->income[$source->value];

            if ($amount !== null && Money::isPositive($amount)) {
                $slices[] = ['label' => __('admin.revenue.sources.' . $source->value), 'value' => (float) $amount, 'tone' => self::SOURCE_TONES[$source->value]];
            }
        }

        return $slices;
    }

    /**
     * @return list<array{label: string, income: string, share: string}>
     */
    public function categories(): array
    {
        $locale = app()->getLocale();
        $total = $this->report->current->totalIncome();

        return array_map(fn (array $row): array => [
            'label' => $row['name'][$locale] ?? $row['name']['en'] ?? __('admin.revenue.uncategorised'),
            'income' => $this->money($row['income']),
            'share' => $this->share($row['income'], $total),
        ], $this->report->categories);
    }

    public function showsSellers(): bool
    {
        return $this->report->sellers !== null;
    }

    /**
     * @return list<array{name: string, url: string|null, commission: string, orders: int}>
     */
    public function sellers(): array
    {
        return array_map(fn (array $row): array => [
            'name' => $row['name'] !== '' ? $row['name'] : __('admin.revenue.deleted_account'),
            'url' => route('admin.finance.statements.show', $row['id']),
            'commission' => $this->money($row['commission']),
            'orders' => $row['orders'],
        ], $this->report->sellers ?? []);
    }

    /**
     * @return list<array{when: string, type: string, amount: string, refund: bool, subject: string|null, links: list<array{label: string, url: string}>}>
     */
    public function transactions(): array
    {
        return array_values(array_map(fn (RevenueLine $line): array => [
            'when' => $line->at->format('Y-m-d H:i'),
            'type' => (string) __('admin.revenue.types.' . $line->type),
            'amount' => $this->signed($line),
            'refund' => Money::isPositive($line->debit),
            'subject' => $line->adTitle,
            'links' => $this->links($line),
        ], $this->lines->items()));
    }

    /**
     * @return Paginator<int, RevenueLine>
     */
    public function paginator(): Paginator
    {
        return $this->lines;
    }

    public function isEmpty(): bool
    {
        return $this->report->revenueAccountId === null
            || ($this->lines->items() === [] && Money::isZero($this->report->current->totalIncome()) && Money::isZero($this->report->current->refunds));
    }

    /**
     * @param array<string, string> $query
     */
    public function exportUrl(array $query): string
    {
        return route('admin.finance.revenue.export', $query);
    }

    /**
     * @return array<string, string>
     */
    public function periodQuery(): array
    {
        $window = $this->report->window;
        $query = ['period' => $window->range->value];

        return $window->range === RevenueRange::CUSTOM
            ? $query + ['from' => $window->start->toDateString(), 'to' => $window->end->subSecond()->toDateString()]
            : $query;
    }

    /**
     * @return array{key: string, label: string, value: string, change: int|null|false, caption: string, icon: string}
     */
    private function kpi(string $key, string $now, string $before, string $icon, bool $trend = true): array
    {
        return [
            'key' => $key,
            'label' => __('admin.revenue.kpis.' . $key),
            'value' => $this->money($now),
            'change' => $trend ? Trend::between($now, $before)['change'] : false,
            'caption' => __('admin.revenue.vs', ['previous' => $this->money($before)]),
            'icon' => $icon,
        ];
    }

    /**
     * @return array{key: string, label: string, kind: string, value: string|null, previous: string|null, change: int|null|false, note: string|null}
     */
    private function line(string $key, ?string $now, ?string $before, string $kind = 'line'): array
    {
        $recorded = $now !== null;

        return [
            'key' => $key,
            'label' => (string) __('admin.revenue.statement.' . $key),
            'kind' => $kind,
            'value' => $recorded ? $this->money($now) : null,
            'previous' => $before !== null ? $this->money($before) : null,
            'change' => $recorded && $before !== null ? Trend::between($now, $before)['change'] : false,
            'note' => $recorded ? null : (string) __('admin.revenue.not_recorded_notes.' . $key),
        ];
    }

    /**
     * @return array{key: string, label: string, kind: string, value: null, previous: null, change: false, note: null}
     */
    private function heading(string $key): array
    {
        return ['key' => $key, 'label' => (string) __('admin.revenue.statement.' . $key), 'kind' => 'heading', 'value' => null, 'previous' => null, 'change' => false, 'note' => null];
    }

    private function signed(RevenueLine $line): string
    {
        $net = Money::subtract($line->credit, $line->debit);

        return (Money::isPositive($net) ? '+' : '') . $this->money($net);
    }

    private function share(string $amount, string $total): string
    {
        if (! Money::isPositive($total)) {
            return '—';
        }

        $percent = BigDecimal::of($amount)->multipliedBy(100)->dividedBy($total, 1, RoundingMode::HALF_UP);

        return rtrim(rtrim((string) $percent, '0'), '.') . '%';
    }

    /**
     * Only the pages the viewer may open: the ad and the party's statement.
     * Orders have no admin page of their own; their ad is the way in.
     *
     * @return list<array{label: string, url: string}>
     */
    private function links(RevenueLine $line): array
    {
        $links = [];

        if ($line->adId !== null && $this->viewer->can('ads.view')) {
            $links[] = ['label' => __('admin.revenue.links.ad'), 'url' => route('admin.ads.show', $line->adId)];
        }

        if ($line->partyId !== null) {
            $links[] = ['label' => $line->partyName ?: __('admin.revenue.links.statement'), 'url' => route('admin.finance.statements.show', $line->partyId)];
        }

        return $links;
    }
}
