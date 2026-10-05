<?php

declare(strict_types=1);

namespace Database\Seeders\Demo\Steps;

use App\Actions\Finance\RequestWithdrawalAction;
use App\Actions\Finance\ReviewWithdrawalAction;
use App\Actions\Finance\SubmitSettlementAction;
use App\Enums\LedgerAccountType;
use App\Enums\LedgerOwnerType;
use App\Enums\WithdrawalStatus;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Models\Withdrawal;
use App\Support\Money;
use Database\Seeders\Demo\DemoContext;
use Database\Seeders\Demo\DemoFinance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * What sellers do with the commission they owe and the balance they hold:
 * bank transfers that finance staff approve or reject, netting from the
 * wallet, and withdrawals that are paid, rejected or still waiting, one of
 * them netting the seller's debt on the way out.
 */
final class SeedWalletActivity
{
    private const string WITHDRAWAL_REJECTION = 'The account holder name does not match the bank records.';

    /** @var list<string> amounts in QAR */
    private const array WITHDRAWAL_AMOUNTS = ['350.00', '800.00', '1250.00', '2000.00'];

    private const array WITHDRAWAL_OUTCOMES = [WithdrawalStatus::PAID, WithdrawalStatus::PENDING, WithdrawalStatus::REJECTED];

    private const int EXTRA_BANK_ACCOUNTS = 4;

    public function __construct(
        private readonly DemoFinance $finance,
        private readonly SubmitSettlementAction $submitSettlement,
        private readonly RequestWithdrawalAction $requestWithdrawal,
        private readonly ReviewWithdrawalAction $reviewWithdrawal,
    ) {}

    public function run(DemoContext $context): void
    {
        $debtors = $this->sellersInDebt($context);

        if ($debtors !== []) {
            $this->settleDebts($context, $debtors);
        }

        $this->withdraw($context, $debtors);

        foreach ($context->random->sample($context->members, self::EXTRA_BANK_ACCOUNTS) as $member) {
            $this->finance->bankAccountFor($member);
        }
    }

    /**
     * One step per settlement path, spread over the indebted sellers and
     * each sized from what the seller still owes at that point, so even a
     * single debtor goes through all of them.
     *
     * @param non-empty-list<User> $debtors
     */
    private function settleDebts(DemoContext $context, array $debtors): void
    {
        $steps = [
            fn (User $seller, string $owed, Carbon $at) => $this->finance->reviewTransfer($context, $this->finance->submitTransfer($context, $seller, $owed, $at), false, $at->copy()->addHours(20)),
            fn (User $seller, string $owed, Carbon $at) => $this->finance->reviewTransfer($context, $this->finance->submitTransfer($context, $seller, $this->half($owed), $at), true, $at->copy()->addHours(6)),
            fn (User $seller, string $owed, Carbon $at) => $this->finance->submitTransfer($context, $seller, $this->half($owed), $at),
            fn (User $seller, string $owed, Carbon $at) => $this->netFromWallet($context, $seller, $this->half($owed), $at),
        ];

        foreach ($steps as $index => $step) {
            $seller = $debtors[$index % count($debtors)];
            $owed = $this->finance->openDebt($seller);

            if (Money::compare($owed, '0.02') >= 0) {
                $step($seller, $owed, $context->clock->daysAgo(4 - $index, $context->random->int(0, 300)));
            }
        }
    }

    private function netFromWallet(DemoContext $context, User $seller, string $amount, Carbon $at): void
    {
        $this->finance->creditWallet($context, $seller, $amount, $at->copy()->subHour());
        $context->clock->at($at, fn () => $this->submitSettlement->fromWallet($seller, $amount));
    }

    /**
     * The first withdrawal comes from a seller who still owes commission, so
     * the request nets the debt first; the rest are paid, left pending or
     * rejected.
     *
     * @param list<User> $debtors
     */
    private function withdraw(DemoContext $context, array $debtors): void
    {
        $others = array_values(array_filter($context->members, static fn (User $member): bool => ! in_array($member, $debtors, true)));
        $count = max(count(self::WITHDRAWAL_OUTCOMES), intdiv(count($context->members), 6));
        $sellers = [...array_slice($debtors, 0, 1), ...$context->random->sample($others, $count)];

        foreach ($sellers as $index => $seller) {
            $amount = $context->random->pick(self::WITHDRAWAL_AMOUNTS);
            $requestedAt = $context->clock->daysAgo($index === 0 ? 0 : $context->random->int(1, 6), $context->random->int(120, 600));
            $this->finance->bankAccountFor($seller);
            $this->finance->ensureWithdrawable($context, $seller, $amount, $requestedAt->copy()->subDay());
            $withdrawal = $context->clock->at($requestedAt, fn (): Withdrawal => ($this->requestWithdrawal)($seller, $amount, null));

            $this->review($context, $withdrawal, self::WITHDRAWAL_OUTCOMES[$index % count(self::WITHDRAWAL_OUTCOMES)], $requestedAt->copy()->addHours($context->random->int(4, 30)));
        }
    }

    private function review(DemoContext $context, Withdrawal $withdrawal, WithdrawalStatus $outcome, Carbon $at): void
    {
        $admin = $context->superAdmin();

        match ($outcome) {
            WithdrawalStatus::PENDING => null,
            WithdrawalStatus::PAID => $context->clock->at($at, fn () => $this->reviewWithdrawal->markPaid($admin, $withdrawal, 'PAY-' . strtoupper(Str::random(8)))),
            WithdrawalStatus::REJECTED => $context->clock->at($at, fn () => $this->reviewWithdrawal->reject($admin, $withdrawal, self::WITHDRAWAL_REJECTION)),
        };
    }

    /**
     * Members who owe commission, biggest debt first, in one query.
     *
     * @return list<User>
     */
    private function sellersInDebt(DemoContext $context): array
    {
        $ownerIds = LedgerAccount::query()
            ->where('owner_type', LedgerOwnerType::USER->value)
            ->where('type', LedgerAccountType::USER_COMMISSION_RECEIVABLE->value)
            ->where('balance', '>', 0)
            ->orderByDesc('balance')
            ->pluck('owner_id')
            ->all();

        $membersById = array_column(array_map(static fn (User $member): array => [$member->id, $member], $context->members), 1, 0);

        return array_values(array_filter(array_map(static fn (mixed $id): ?User => $membersById[$id] ?? null, $ownerIds)));
    }

    private function half(string $amount): string
    {
        return Money::percentOf($amount, '50');
    }
}
