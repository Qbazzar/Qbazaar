<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Ledger\LedgerActor;
use App\Data\Ledger\LedgerReference;
use App\Enums\FinanceNotice;
use App\Enums\SettlementStatus;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\CommissionSettlement;
use App\Models\User;
use App\Notifications\Finance\WalletActivityNotification;
use App\Services\Ledger\LedgerRecipes;
use Closure;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Finance staff confirm a bank-transfer settlement against the bank
 * statement, or reject it with a reason. Approval posts the settlement to
 * the ledger in the same transaction as the status change.
 *
 * The settlement row is locked first and the ledger accounts after it, and
 * only a pending settlement can be reviewed, so a double click or two staff
 * acting at once never post twice.
 */
class ReviewSettlementAction
{
    public function __construct(
        private readonly LedgerRecipes $recipes,
    ) {}

    public function approve(User $admin, CommissionSettlement $settlement): CommissionSettlement
    {
        $approved = $this->review($admin, $settlement, SettlementStatus::APPROVED, function (CommissionSettlement $locked) use ($admin): void {
            $this->recipes->settleCommissionByBankTransfer(
                $locked->user_id ?? throw new LogicException("Settlement [{$locked->id}] has no seller."),
                $locked->amount,
                LedgerReference::settlement($locked->id),
                LedgerActor::admin($admin),
            );
        });

        $this->notifySeller($approved, FinanceNotice::SETTLEMENT_APPROVED);

        return $approved;
    }

    public function reject(User $admin, CommissionSettlement $settlement, string $reason): CommissionSettlement
    {
        $rejected = $this->review($admin, $settlement, SettlementStatus::REJECTED, function (CommissionSettlement $locked) use ($reason): void {
            $locked->rejection_reason = $reason;
        });

        $this->notifySeller($rejected, FinanceNotice::SETTLEMENT_REJECTED);

        return $rejected;
    }

    /**
     * @param Closure(CommissionSettlement): void $apply
     */
    private function review(User $admin, CommissionSettlement $settlement, SettlementStatus $next, Closure $apply): CommissionSettlement
    {
        $reviewed = DB::transaction(function () use ($admin, $settlement, $next, $apply): CommissionSettlement {
            /** @var CommissionSettlement $locked */
            $locked = CommissionSettlement::query()->whereKey($settlement->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->canTransitionTo($next)) {
                throw new DomainException(ErrorCode::SETTLEMENT_NOT_PENDING);
            }

            $apply($locked);

            $locked->forceFill([
                'status' => $next,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ])->save();

            return $locked;
        });

        activity('finance')
            ->causedBy($admin)
            ->performedOn($reviewed)
            ->event('settlement_' . $next->value)
            ->withProperties(['amount' => $reviewed->amount, 'reason' => $reviewed->rejection_reason])
            ->log('Commission settlement ' . $next->value);

        return $reviewed;
    }

    private function notifySeller(CommissionSettlement $settlement, FinanceNotice $notice): void
    {
        $settlement->user?->notify(new WalletActivityNotification($notice, $settlement->id, [
            'amount' => $settlement->amount,
            'reason' => $settlement->rejection_reason,
        ]));
    }
}
