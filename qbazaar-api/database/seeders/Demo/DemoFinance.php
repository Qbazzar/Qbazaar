<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Actions\Finance\ReviewSettlementAction;
use App\Actions\Finance\SubmitSettlementAction;
use App\Data\Ledger\LedgerActor;
use App\Data\Ledger\LedgerReference;
use App\Enums\LedgerReferenceType;
use App\Models\BankAccount;
use App\Models\CommissionSettlement;
use App\Models\User;
use App\Services\Finance\BankAccountService;
use App\Services\Ledger\LedgerRecipes;
use App\Services\Ledger\WalletService;
use App\Support\Iban;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Money the way staff and sellers move it: every call goes through the
 * production actions and ledger recipes, so a demo settlement or top-up is
 * indistinguishable from a real one.
 */
final class DemoFinance
{
    private const string BANK_CODE = 'DEMO';

    private const string SETTLEMENT_REJECTION = 'The transfer reference does not match any payment we received.';

    private int $ibanSequence = 0;

    public function __construct(
        private readonly SubmitSettlementAction $submitSettlement,
        private readonly ReviewSettlementAction $reviewSettlement,
        private readonly BankAccountService $bankAccounts,
        private readonly LedgerRecipes $recipes,
        private readonly WalletService $wallets,
        private readonly DemoMedia $media,
    ) {}

    /**
     * The seller sends a bank transfer for part of their commission debt and
     * uploads the receipt; it waits for finance staff.
     */
    public function submitTransfer(DemoContext $context, User $seller, string $amount, Carbon $at): CommissionSettlement
    {
        $proof = $this->media->transferProof('Bank transfer ' . $amount . ' QAR');

        return $context->clock->at($at, fn (): CommissionSettlement => $this->submitSettlement->byBankTransfer(
            $seller,
            $amount,
            'TRF-' . strtoupper(Str::random(8)),
            $proof,
        ));
    }

    public function reviewTransfer(DemoContext $context, CommissionSettlement $settlement, bool $approve, Carbon $at): CommissionSettlement
    {
        $admin = $context->superAdmin();

        return $context->clock->at($at, fn (): CommissionSettlement => $approve
            ? $this->reviewSettlement->approve($admin, $settlement)
            : $this->reviewSettlement->reject($admin, $settlement, self::SETTLEMENT_REJECTION));
    }

    /**
     * A seller at the commission debt ceiling cannot take new orders, so
     * they pay their debt by bank transfer and an admin approves it first.
     */
    public function ensureCanTakeOrders(DemoContext $context, User $seller, Carbon $at): void
    {
        if ($this->wallets->canAcceptOrders($seller->id)) {
            return;
        }

        $settlement = $this->submitTransfer($context, $seller, $this->openDebt($seller), $at->copy()->subHours(3));
        $this->reviewTransfer($context, $settlement, true, $at->copy()->subHour());
    }

    /**
     * Commission the seller owes that no transfer awaiting review covers yet.
     */
    public function openDebt(User $seller): string
    {
        $owed = Money::subtract($this->wallets->summary($seller->id)->commissionDebt, CommissionSettlement::pendingTransferAmount($seller->id));

        return Money::isPositive($owed) ? $owed : Money::ZERO;
    }

    /**
     * Credits the wallet by the admin's adjustment, the way support gives
     * goodwill credit.
     */
    public function creditWallet(DemoContext $context, User $member, string $amount, Carbon $at): void
    {
        $context->clock->at($at, fn () => $this->recipes->adjustWallet(
            $member->id,
            $amount,
            'Goodwill credit',
            new LedgerReference(LedgerReferenceType::ADJUSTMENT, (string) Str::ulid()),
            LedgerActor::admin($context->superAdmin()),
        ));
    }

    /**
     * Tops the wallet up so that $amount can be spent or withdrawn on top of
     * the commission the member still owes.
     */
    public function ensureWithdrawable(DemoContext $context, User $member, string $amount, Carbon $at): void
    {
        $wallet = $this->wallets->summary($member->id);
        $missing = Money::subtract(Money::add($amount, $wallet->commissionDebt), $wallet->available);

        if (Money::isPositive($missing)) {
            $this->creditWallet($context, $member, $missing, $at);
        }
    }

    public function bankAccountFor(User $member): BankAccount
    {
        return $this->bankAccounts->list($member)->first()
            ?? $this->bankAccounts->create($member, $member->full_name, $this->nextIban(), 'Demo Bank', true);
    }

    /**
     * A well-formed Qatari IBAN on a bank code that does not exist, so it
     * passes validation but can never be somebody's real account.
     */
    private function nextIban(): string
    {
        $account = sprintf('%021d', ++$this->ibanSequence);

        for ($check = 2; $check <= 98; $check++) {
            $iban = sprintf('QA%02d%s%s', $check, self::BANK_CODE, $account);

            if (Iban::isValid($iban)) {
                return $iban;
            }
        }

        throw new RuntimeException('No valid check digits for the demo IBAN.');
    }
}
