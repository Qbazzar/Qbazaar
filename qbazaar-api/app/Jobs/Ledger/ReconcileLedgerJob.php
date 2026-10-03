<?php

declare(strict_types=1);

namespace App\Jobs\Ledger;

use App\Enums\QueueName;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\Ledger\LedgerReconciliationFailedNotification;
use App\Services\Admin\StaffDirectory;
use App\Services\Ledger\LedgerReconciler;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Daily proof that the books add up (see LedgerReconciler). Any mismatch is
 * logged in full and every active staff member with `finance.view` is
 * alerted; nothing is corrected automatically, because a fix must be a
 * deliberate reversal or adjustment posted by a person.
 */
class ReconcileLedgerJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const string ALERT_PERMISSION = 'finance.view';

    public int $tries = 2;

    /** @var list<int> */
    public array $backoff = [600];

    public int $timeout = 900;

    public int $uniqueFor = 3600;

    public function __construct()
    {
        $this->onQueue(QueueName::LOW);
    }

    public function handle(LedgerReconciler $reconciler, StaffDirectory $staff): void
    {
        $report = $reconciler->run();

        if ($report->isClean()) {
            Log::info('Ledger reconciliation passed.', ['total_debits' => $report->totalDebits]);

            return;
        }

        Log::critical('Ledger reconciliation failed.', $report->toArray());

        $recipients = User::query()
            ->whereIn('id', $staff->idsWithPermission(self::ALERT_PERMISSION))
            ->where('status', UserStatus::ACTIVE->value)
            ->get();

        Notification::send($recipients, new LedgerReconciliationFailedNotification(
            accountMismatches: count($report->accountMismatches),
            unbalancedTransactions: count($report->unbalancedTransactionIds),
            malformedEntries: count($report->malformedEntryIds),
            trialBalanceHolds: $report->trialBalanceHolds(),
        ));
    }
}
