<?php

declare(strict_types=1);

namespace App\Services\Ledger;

use App\Data\Ledger\LedgerActor;
use App\Data\Ledger\LedgerLine;
use App\Data\Ledger\LedgerReference;
use App\Enums\LedgerAccountType as Account;
use App\Enums\LedgerTransactionType as Flow;
use App\Models\LedgerTransaction;
use App\Models\Order;
use App\Support\Money;
use InvalidArgumentException;
use LogicException;

/**
 * Every money flow of the marketplace as a named posting. Callers say what
 * happened ("commission charged on this order"); the debit and credit lines
 * live here and in qbazaar-contracts/LEDGER.md, nowhere else.
 *
 * Each flow is keyed by its business record, so running it again for the
 * same record returns the first posting instead of moving money twice.
 */
class LedgerRecipes
{
    public function __construct(
        private readonly LedgerService $ledger,
    ) {}

    /**
     * Cash handover: the seller kept the whole payment, so the commission
     * becomes a debt the seller owes the platform. Nothing is posted for a
     * zero commission.
     */
    public function chargeCommission(Order $order, LedgerActor $actor): ?LedgerTransaction
    {
        if (Money::isZero($order->commission_amount)) {
            return null;
        }

        return $this->postFor(LedgerReference::order($order->id), Flow::COMMISSION_CHARGED, $actor, [
            LedgerLine::debit(Account::USER_COMMISSION_RECEIVABLE, $order->commission_amount, $this->sellerOf($order)),
            LedgerLine::credit(Account::PLATFORM_REVENUE, $order->commission_amount),
        ]);
    }

    /**
     * The seller paid their commission debt into the platform's bank account.
     */
    public function settleCommissionByBankTransfer(string $sellerId, string $amount, LedgerReference $settlement, LedgerActor $actor): LedgerTransaction
    {
        return $this->postFor($settlement, Flow::COMMISSION_SETTLED, $actor, [
            LedgerLine::debit(Account::PLATFORM_BANK, $amount),
            LedgerLine::credit(Account::USER_COMMISSION_RECEIVABLE, $amount, $sellerId),
        ]);
    }

    /**
     * Netting: the seller's commission debt is paid out of their own wallet.
     */
    public function settleCommissionFromWallet(string $sellerId, string $amount, LedgerReference $settlement, LedgerActor $actor): LedgerTransaction
    {
        return $this->postFor($settlement, Flow::COMMISSION_SETTLED, $actor, [
            LedgerLine::debit(Account::USER_WALLET, $amount, $sellerId),
            LedgerLine::credit(Account::USER_COMMISSION_RECEIVABLE, $amount, $sellerId),
        ]);
    }

    /**
     * Escrow payments (bank transfer or gateway): the buyer's money is held
     * by the platform until the handover.
     */
    public function receiveOrderPayment(Order $order, Account $collectedInto, LedgerActor $actor): LedgerTransaction
    {
        $this->assertCollectionAccount($collectedInto);

        return $this->postFor(LedgerReference::order($order->id), Flow::ORDER_PAYMENT_RECEIVED, $actor, [
            LedgerLine::debit($collectedInto, $order->total),
            LedgerLine::credit(Account::PLATFORM_ESCROW, $order->total),
        ]);
    }

    /**
     * Handover of an escrow order: the seller's wallet gets the total minus
     * the commission, which the platform keeps as revenue.
     */
    public function releaseEscrow(Order $order, LedgerActor $actor): LedgerTransaction
    {
        $sellerShare = Money::subtract($order->total, $order->commission_amount);

        $lines = [LedgerLine::debit(Account::PLATFORM_ESCROW, $order->total)];

        if (Money::isPositive($sellerShare)) {
            $lines[] = LedgerLine::credit(Account::USER_WALLET, $sellerShare, $this->sellerOf($order));
        }

        if (Money::isPositive($order->commission_amount)) {
            $lines[] = LedgerLine::credit(Account::PLATFORM_REVENUE, $order->commission_amount);
        }

        return $this->postFor(LedgerReference::order($order->id), Flow::ESCROW_RELEASED, $actor, $lines);
    }

    /**
     * A cancelled or lost-dispute escrow order: the held money goes back out
     * the way it came in.
     */
    public function refundOrderPayment(Order $order, Account $refundedFrom, LedgerActor $actor): LedgerTransaction
    {
        $this->assertCollectionAccount($refundedFrom);

        return $this->postFor(LedgerReference::order($order->id), Flow::REFUND, $actor, [
            LedgerLine::debit(Account::PLATFORM_ESCROW, $order->total),
            LedgerLine::credit($refundedFrom, $order->total),
        ]);
    }

    /**
     * The amount leaves the wallet at once, so it cannot be spent twice
     * while the admin reviews the request.
     */
    public function requestWithdrawal(string $userId, string $amount, LedgerReference $withdrawal, LedgerActor $actor): LedgerTransaction
    {
        return $this->postFor($withdrawal, Flow::WITHDRAWAL_REQUESTED, $actor, [
            LedgerLine::debit(Account::USER_WALLET, $amount, $userId),
            LedgerLine::credit(Account::PLATFORM_PAYOUTS_PAYABLE, $amount),
        ]);
    }

    public function payWithdrawal(string $amount, LedgerReference $withdrawal, LedgerActor $admin): LedgerTransaction
    {
        return $this->postFor($withdrawal, Flow::WITHDRAWAL_PAID, $admin, [
            LedgerLine::debit(Account::PLATFORM_PAYOUTS_PAYABLE, $amount),
            LedgerLine::credit(Account::PLATFORM_BANK, $amount),
        ]);
    }

    /**
     * Puts a rejected withdrawal back into the wallet by reversing the request.
     */
    public function rejectWithdrawal(LedgerReference $withdrawal, LedgerActor $admin, ?string $reason = null): LedgerTransaction
    {
        $request = $this->ledger->findByKey($withdrawal->keyFor(Flow::WITHDRAWAL_REQUESTED))
            ?? throw new LogicException("Withdrawal [{$withdrawal->id}] was never requested.");

        return $this->ledger->reverse($request, $withdrawal->keyFor(Flow::WITHDRAWAL_REJECTED), $admin, $reason, Flow::WITHDRAWAL_REJECTED);
    }

    public function purchasePromotionFromWallet(string $userId, string $amount, LedgerReference $promotion, LedgerActor $actor): LedgerTransaction
    {
        return $this->postFor($promotion, Flow::PROMOTION_PURCHASED, $actor, [
            LedgerLine::debit(Account::USER_WALLET, $amount, $userId),
            LedgerLine::credit(Account::PLATFORM_REVENUE, $amount),
        ]);
    }

    public function purchasePromotionByBankTransfer(string $amount, LedgerReference $promotion, LedgerActor $admin): LedgerTransaction
    {
        return $this->postFor($promotion, Flow::PROMOTION_PURCHASED, $admin, [
            LedgerLine::debit(Account::PLATFORM_BANK, $amount),
            LedgerLine::credit(Account::PLATFORM_REVENUE, $amount),
        ]);
    }

    /**
     * An admin correction of a wallet: a positive amount credits the user,
     * a negative one debits them. The other side is the adjustments account,
     * so every correction stays visible in the platform's books.
     */
    public function adjustWallet(string $userId, string $signedAmount, string $memo, LedgerReference $adjustment, LedgerActor $admin): LedgerTransaction
    {
        $amount = ltrim($signedAmount, '-');

        $lines = Money::isNegative($signedAmount)
            ? [LedgerLine::debit(Account::USER_WALLET, $amount, $userId), LedgerLine::credit(Account::PLATFORM_ADJUSTMENTS, $amount)]
            : [LedgerLine::debit(Account::PLATFORM_ADJUSTMENTS, $amount), LedgerLine::credit(Account::USER_WALLET, $amount, $userId)];

        return $this->postFor($adjustment, Flow::ADJUSTMENT, $admin, $lines, $memo);
    }

    /**
     * @param list<LedgerLine> $lines
     */
    private function postFor(LedgerReference $reference, Flow $flow, LedgerActor $actor, array $lines, ?string $memo = null): LedgerTransaction
    {
        return $this->ledger->post($flow, $lines, $reference->keyFor($flow), $reference, $actor, $memo);
    }

    private function sellerOf(Order $order): string
    {
        return $order->seller_id ?? throw new LogicException("Order [{$order->id}] has no seller.");
    }

    private function assertCollectionAccount(Account $account): void
    {
        if (! in_array($account, [Account::PLATFORM_BANK, Account::PLATFORM_GATEWAY_CLEARING], true)) {
            throw new InvalidArgumentException("Payments are collected into the bank or gateway clearing, not [{$account->value}].");
        }
    }
}
