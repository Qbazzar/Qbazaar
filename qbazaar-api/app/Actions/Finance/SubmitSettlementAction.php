<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Ledger\LedgerActor;
use App\Data\Ledger\LedgerReference;
use App\Enums\FinanceNotice;
use App\Enums\FinanceReview;
use App\Enums\LedgerAccountType;
use App\Enums\SettlementMethod;
use App\Enums\SettlementStatus;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\CommissionSettlement;
use App\Models\User;
use App\Notifications\Finance\WalletActivityNotification;
use App\Services\Finance\FinanceStaff;
use App\Services\Ledger\LedgerAccounts;
use App\Services\Ledger\LedgerRecipes;
use App\Services\Media\UploadedFileNamer;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * A seller pays off their commission debt, either by a bank transfer that
 * finance staff confirm against the bank statement, or at once by netting
 * it from their own wallet balance.
 */
class SubmitSettlementAction
{
    public function __construct(
        private readonly LedgerRecipes $recipes,
        private readonly LedgerAccounts $accounts,
        private readonly UploadedFileNamer $fileNamer,
        private readonly FinanceStaff $staff,
    ) {}

    /**
     * Only one bank transfer waits for review at a time; the database
     * enforces it, so two concurrent submissions cannot both pass.
     */
    public function byBankTransfer(User $seller, string $amount, string $bankReference, UploadedFile $proof): CommissionSettlement
    {
        $amount = $this->withinDebt($seller, $amount);

        $settlement = $this->newSettlement($seller, SettlementMethod::BANK_TRANSFER, SettlementStatus::PENDING, $amount);
        $settlement->bank_reference = $bankReference;

        try {
            $settlement->save();
        } catch (UniqueConstraintViolationException) {
            throw new DomainException(ErrorCode::SETTLEMENT_PENDING_EXISTS);
        }

        // Stored after the row is saved and outside any transaction, so a
        // slow upload to remote storage never holds a database lock.
        try {
            $settlement->addMedia($proof)
                ->usingFileName($this->fileNamer->nameFor($proof))
                ->toMediaCollection(CommissionSettlement::PROOF_COLLECTION);
        } catch (Throwable $exception) {
            $settlement->delete();

            throw $exception;
        }

        $seller->notify(new WalletActivityNotification(FinanceNotice::SETTLEMENT_SUBMITTED, $settlement->id, ['amount' => $amount]));
        $this->staff->requestReview(FinanceReview::SETTLEMENT, $amount);

        return $settlement;
    }

    /**
     * Netting moves money between the seller's own two accounts, so it is
     * posted and approved at once. The ledger refuses more than the wallet
     * holds (WALLET_001) or more than is owed (WALLET_002).
     */
    public function fromWallet(User $seller, string $amount): CommissionSettlement
    {
        $amount = $this->withinDebt($seller, $amount);

        $settlement = DB::transaction(function () use ($seller, $amount): CommissionSettlement {
            $settlement = $this->newSettlement($seller, SettlementMethod::WALLET, SettlementStatus::APPROVED, $amount);
            $settlement->reviewed_at = now();
            $settlement->save();

            $this->recipes->settleCommissionFromWallet(
                $seller->id,
                $amount,
                LedgerReference::settlement($settlement->id),
                LedgerActor::user($seller),
            );

            return $settlement;
        });

        $seller->notify(new WalletActivityNotification(FinanceNotice::SETTLEMENT_APPROVED, $settlement->id, ['amount' => $amount]));

        return $settlement;
    }

    /**
     * An early answer for the client; the ledger re-checks under its locks.
     */
    private function withinDebt(User $seller, string $amount): string
    {
        $amount = Money::of($amount);
        $owed = $this->accounts->balanceOf(LedgerAccountType::USER_COMMISSION_RECEIVABLE, $seller->id);

        if (Money::compare($amount, $owed) > 0) {
            throw new DomainException(ErrorCode::WALLET_EXCEEDS_COMMISSION_DEBT);
        }

        return $amount;
    }

    private function newSettlement(User $seller, SettlementMethod $method, SettlementStatus $status, string $amount): CommissionSettlement
    {
        $settlement = new CommissionSettlement;
        $settlement->forceFill([
            'user_id' => $seller->id,
            'method' => $method,
            'status' => $status,
            'amount' => $amount,
        ]);

        return $settlement;
    }
}
