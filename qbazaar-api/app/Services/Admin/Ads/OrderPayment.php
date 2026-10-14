<?php

declare(strict_types=1);

namespace App\Services\Admin\Ads;

use App\Data\Admin\AdDeal\PaymentLine;
use App\Data\Admin\AdDeal\PaymentSummary;
use App\Enums\LedgerAccountType;
use App\Enums\LedgerActorType;
use App\Enums\LedgerReferenceType;
use App\Enums\LedgerTransactionType as Flow;
use App\Enums\OrderStatus;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\Order;
use App\Models\User;
use App\Services\Ledger\LedgerAccounts;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * The payment and escrow state of an order, read from the journal
 * transactions posted for it (served by ledger_transactions_reference_idx).
 * Money is only read or subtracted with Money here; the view prints strings.
 *
 * Only cash exists today, so nothing is held and no gateway reference is
 * stored; the escrow fields come alive when an electronic method does.
 */
final class OrderPayment
{
    /** An order has a handful of postings: payment, release or refund, commission. */
    private const int LINE_LIMIT = 10;

    public function __construct(private readonly LedgerAccounts $accounts) {}

    /**
     * @return Collection<int, LedgerTransaction>
     */
    public function transactionsOf(Order $order): Collection
    {
        return LedgerTransaction::query()
            ->where('reference_type', LedgerReferenceType::ORDER->value)
            ->where('reference_id', $order->id)
            ->with('entries')
            ->orderBy('posted_at')
            ->orderBy('id')
            ->limit(self::LINE_LIMIT)
            ->get();
    }

    /**
     * @param Collection<int, LedgerTransaction> $transactions
     * @param array<string, string> $actorNames staff and users by id
     */
    public function summarize(Order $order, Collection $transactions, array $actorNames): PaymentSummary
    {
        $byType = $transactions->keyBy(fn (LedgerTransaction $transaction): string => $transaction->type->value);
        $received = $byType->has(Flow::ORDER_PAYMENT_RECEIVED->value);
        $released = $byType->get(Flow::ESCROW_RELEASED->value);
        $refunded = $byType->has(Flow::REFUND->value);
        $escrow = $this->escrowState($received, $released !== null, $refunded);
        $charged = $byType->get(Flow::COMMISSION_CHARGED->value);

        return new PaymentSummary(
            status: $this->statusOf($order, $received, $refunded),
            escrow: $escrow,
            payout: $this->payoutOf($order, $escrow),
            escrowAt: $released === null ? null : CarbonImmutable::instance($released->posted_at),
            escrowActor: $released === null ? null : $this->actorName($released, $actorNames),
            total: $order->total,
            commission: $order->commission_amount,
            sellerNet: Money::subtract($order->total, $order->commission_amount),
            commissionCharged: $charged !== null,
            commissionRefunded: $byType->has(Flow::COMMISSION_REFUNDED->value),
            sellerCommissionDebt: $this->commissionDebtOf($order),
            lines: array_values($transactions->map(fn (LedgerTransaction $transaction): PaymentLine => $this->lineOf($transaction, $actorNames))->all()),
        );
    }

    /**
     * @param Collection<int, LedgerTransaction> $transactions
     * @return list<string>
     */
    public function actorIds(Collection $transactions): array
    {
        return array_values($transactions
            ->filter(fn (LedgerTransaction $transaction): bool => $transaction->created_by_id !== null && $transaction->created_by_type !== LedgerActorType::SYSTEM)
            ->pluck('created_by_id')
            ->unique()
            ->map(fn (mixed $id): string => (string) $id)
            ->all());
    }

    /**
     * @param list<string> $ids
     * @return array<string, string>
     */
    public function actorNames(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return User::query()->whereIn('id', $ids)->get(['id', 'full_name', 'email'])
            ->mapWithKeys(fn (User $user): array => [$user->id => $user->full_name ?: $user->email])
            ->all();
    }

    private function escrowState(bool $received, bool $released, bool $refunded): string
    {
        return match (true) {
            $refunded => 'refunded',
            $released => 'released',
            $received => 'held',
            default => 'none',
        };
    }

    private function statusOf(Order $order, bool $received, bool $refunded): string
    {
        return match (true) {
            $refunded => 'refunded',
            $received, $order->status === OrderStatus::COMPLETED => 'paid',
            $order->status === OrderStatus::CANCELLED => 'not_charged',
            default => 'pending',
        };
    }

    private function payoutOf(Order $order, string $escrow): string
    {
        return match (true) {
            $escrow !== 'none' => $escrow,
            $order->status === OrderStatus::COMPLETED => 'paid_direct',
            $order->status === OrderStatus::CANCELLED => 'none',
            default => 'pending',
        };
    }

    private function commissionDebtOf(Order $order): string
    {
        return $order->seller_id === null
            ? Money::ZERO
            : $this->accounts->balanceOf(LedgerAccountType::USER_COMMISSION_RECEIVABLE, $order->seller_id);
    }

    /**
     * @param array<string, string> $actorNames
     */
    private function lineOf(LedgerTransaction $transaction, array $actorNames): PaymentLine
    {
        $debits = $transaction->entries->reduce(fn (string $sum, LedgerEntry $entry): string => Money::add($sum, $entry->debit), Money::ZERO);

        return new PaymentLine($transaction->type, CarbonImmutable::instance($transaction->posted_at), $this->actorName($transaction, $actorNames), $debits);
    }

    /**
     * @param array<string, string> $actorNames
     */
    private function actorName(LedgerTransaction $transaction, array $actorNames): string
    {
        return match (true) {
            $transaction->created_by_type === LedgerActorType::SYSTEM => (string) __('admin.timeline.system'),
            default => $actorNames[(string) $transaction->created_by_id] ?? (string) __('admin.timeline.system'),
        };
    }
}
