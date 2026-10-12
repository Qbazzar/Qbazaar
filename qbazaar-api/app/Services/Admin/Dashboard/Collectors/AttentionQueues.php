<?php

declare(strict_types=1);

namespace App\Services\Admin\Dashboard\Collectors;

use App\Enums\OrderStatus;
use App\Enums\ReportStatus;
use App\Enums\SettlementStatus;
use App\Enums\SupportTicketStatus;
use App\Enums\WithdrawalStatus;
use App\Models\CommissionSettlement;
use App\Models\Order;
use App\Models\Report;
use App\Models\SupportTicket;
use App\Models\Withdrawal;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The work queues staff clear, each as one aggregate over its status index:
 * how many are waiting, how long the oldest has waited and, for money, how
 * much. `href` is the admin page that handles the queue.
 */
final class AttentionQueues
{
    /**
     * @return array<string, mixed>
     */
    public function reports(): array
    {
        return $this->queue(
            Report::query()->where('status', ReportStatus::PENDING->value),
            'created_at',
            route('admin.reports.index', ['status' => ReportStatus::PENDING->value], false),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function tickets(): array
    {
        return $this->queue(
            SupportTicket::query()->where('status', SupportTicketStatus::OPEN->value),
            'created_at',
            route('admin.support.index', ['status' => SupportTicketStatus::OPEN->value], false),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function settlements(): array
    {
        return $this->queue(
            CommissionSettlement::query()->where('status', SettlementStatus::PENDING->value),
            'created_at',
            route('admin.finance.settlements.index', [], false),
            'amount',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function withdrawals(): array
    {
        return $this->queue(
            Withdrawal::query()->where('status', WithdrawalStatus::PENDING->value),
            'created_at',
            route('admin.finance.withdrawals.index', [], false),
            'amount',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function disputes(): array
    {
        return $this->queue(
            Order::query()->where('status', OrderStatus::DISPUTED->value),
            'disputed_at',
            route('admin.finance.disputes.index', [], false),
        );
    }

    /**
     * Failed queue jobs, read from the failed-jobs table (no Redis round trip).
     *
     * @return array<string, mixed>
     */
    public function failedJobs(): array
    {
        $table = (string) config('queue.failed.table', 'failed_jobs');
        $row = DB::table($table)->selectRaw('COUNT(*) as total, MIN(failed_at) as oldest')->get()->sole();

        return ['count' => (int) $row->total, 'oldest' => $row->oldest, 'amount' => null, 'href' => null];
    }

    /**
     * @param Builder<*> $query
     * @return array{count: int, oldest: string|null, amount: string|null, href: string|null}
     */
    private function queue(Builder $query, string $ageColumn, string $href, ?string $sumColumn = null): array
    {
        $select = "COUNT(*) as total, MIN({$ageColumn}) as oldest" . ($sumColumn ? ", COALESCE(SUM({$sumColumn}), 0) as amount" : '');
        $row = $query->selectRaw($select)->toBase()->get()->sole();

        return [
            'count' => (int) $row->total,
            'oldest' => $row->oldest,
            'amount' => $sumColumn ? Money::round((string) $row->amount) : null,
            'href' => $href,
        ];
    }
}
