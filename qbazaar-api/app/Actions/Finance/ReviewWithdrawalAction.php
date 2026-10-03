<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Ledger\LedgerActor;
use App\Data\Ledger\LedgerReference;
use App\Enums\FinanceNotice;
use App\Enums\LedgerReferenceType;
use App\Enums\WithdrawalStatus;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\User;
use App\Models\Withdrawal;
use App\Notifications\Finance\WalletActivityNotification;
use App\Services\Ledger\LedgerRecipes;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Finance staff record that they transferred a withdrawal, or reject it,
 * which puts the amount back in the seller's wallet. Both postings share
 * the withdrawal's outcome key, so the ledger accepts only one of them;
 * the withdrawal row is locked before the ledger accounts.
 */
class ReviewWithdrawalAction
{
    public function __construct(
        private readonly LedgerRecipes $recipes,
    ) {}

    public function markPaid(User $admin, Withdrawal $withdrawal, string $transferReference): Withdrawal
    {
        $paid = $this->review($admin, $withdrawal, WithdrawalStatus::PAID, function (Withdrawal $locked) use ($admin, $transferReference): void {
            $this->recipes->payWithdrawal($this->referenceOf($locked), LedgerActor::admin($admin));
            $locked->transfer_reference = $transferReference;
        });

        $this->notifySeller($paid, FinanceNotice::WITHDRAWAL_PAID);

        return $paid;
    }

    public function reject(User $admin, Withdrawal $withdrawal, string $reason): Withdrawal
    {
        $rejected = $this->review($admin, $withdrawal, WithdrawalStatus::REJECTED, function (Withdrawal $locked) use ($admin, $reason): void {
            $this->recipes->rejectWithdrawal($this->referenceOf($locked), LedgerActor::admin($admin), $reason);
            $locked->rejection_reason = $reason;
        });

        $this->notifySeller($rejected, FinanceNotice::WITHDRAWAL_REJECTED);

        return $rejected;
    }

    /**
     * @param Closure(Withdrawal): void $apply
     */
    private function review(User $admin, Withdrawal $withdrawal, WithdrawalStatus $next, Closure $apply): Withdrawal
    {
        $reviewed = DB::transaction(function () use ($admin, $withdrawal, $next, $apply): Withdrawal {
            /** @var Withdrawal $locked */
            $locked = Withdrawal::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->canTransitionTo($next)) {
                throw new DomainException(ErrorCode::WITHDRAWAL_NOT_PENDING);
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
            ->event('withdrawal_' . $next->value)
            ->withProperties([
                'amount' => $reviewed->amount,
                'transfer_reference' => $reviewed->transfer_reference,
                'reason' => $reviewed->rejection_reason,
            ])
            ->log('Withdrawal ' . $next->value);

        return $reviewed;
    }

    private function referenceOf(Withdrawal $withdrawal): LedgerReference
    {
        return new LedgerReference(LedgerReferenceType::WITHDRAWAL, $withdrawal->id);
    }

    private function notifySeller(Withdrawal $withdrawal, FinanceNotice $notice): void
    {
        $withdrawal->user?->notify(new WalletActivityNotification($notice, $withdrawal->id, [
            'amount' => $withdrawal->amount,
            'reason' => $withdrawal->rejection_reason,
            'reference' => $withdrawal->transfer_reference,
        ]));
    }
}
