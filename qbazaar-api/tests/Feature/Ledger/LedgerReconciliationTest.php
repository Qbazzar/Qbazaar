<?php

declare(strict_types=1);

use App\Data\Ledger\LedgerActor;
use App\Data\Ledger\LedgerReference;
use App\Enums\LedgerAccountType as Account;
use App\Enums\LedgerReferenceType;
use App\Exceptions\DomainException;
use App\Jobs\Ledger\ReconcileLedgerJob;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\Order;
use App\Models\User;
use App\Notifications\Ledger\LedgerReconciliationFailedNotification;
use App\Services\Admin\StaffDirectory;
use App\Services\Ledger\LedgerRecipes;
use App\Services\Ledger\LedgerReconciler;
use App\Support\Money;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function ledgerRef(LedgerReferenceType $type): LedgerReference
{
    return new LedgerReference($type, (string) Str::ulid());
}

function randomAmount(): string
{
    return Money::of(mt_rand(1, 500_000) . '.' . str_pad((string) mt_rand(0, 99), 2, '0', STR_PAD_LEFT));
}

/**
 * A few hundred postings across every flow, in random order and amounts.
 * Flows that would overdraw an account are refused by the ledger, which is
 * part of what is being exercised.
 */
function postRandomFlows(int $count): void
{
    $recipes = app(LedgerRecipes::class);
    $system = LedgerActor::system();
    $sellers = User::factory()->count(4)->create();
    $requestedWithdrawals = [];

    mt_srand(20261003);

    for ($i = 0; $i < $count; $i++) {
        $seller = $sellers[mt_rand(0, 3)];
        $amount = randomAmount();
        $order = Order::factory()->make(['id' => (string) Str::ulid(), 'ad_id' => null, 'buyer_id' => null, 'seller_id' => $seller->id, 'total' => $amount, 'commission_amount' => Money::percentOf($amount, '7.50')]);

        try {
            match (mt_rand(0, 8)) {
                0 => $recipes->chargeCommission($order, $system),
                1 => $recipes->settleCommissionByBankTransfer($seller->id, Money::percentOf($amount, '1.00'), ledgerRef(LedgerReferenceType::SETTLEMENT), $system),
                2 => $recipes->settleCommissionFromWallet($seller->id, Money::percentOf($amount, '1.00'), ledgerRef(LedgerReferenceType::SETTLEMENT), $system),
                3 => [$recipes->receiveOrderPayment($order, Account::PLATFORM_GATEWAY_CLEARING, $system), $recipes->releaseEscrow($order, $system)],
                4 => [$recipes->receiveOrderPayment($order, Account::PLATFORM_BANK, $system), $recipes->refundOrderPayment($order, Account::PLATFORM_BANK, $system)],
                5 => $requestedWithdrawals[] = [$recipes->requestWithdrawal($seller->id, Money::percentOf($amount, '10.00'), $ref = ledgerRef(LedgerReferenceType::WITHDRAWAL), $system), $ref],
                6 => ($pending = array_pop($requestedWithdrawals)) === null ? null : (mt_rand(0, 1) === 1
                    ? $recipes->payWithdrawal(Money::add($pending[0]->entries[0]->debit, $pending[0]->entries[0]->credit), $pending[1], $system)
                    : $recipes->rejectWithdrawal($pending[1], $system)),
                7 => $recipes->purchasePromotionFromWallet($seller->id, Money::percentOf($amount, '2.00'), ledgerRef(LedgerReferenceType::PROMOTION), $system),
                default => $recipes->adjustWallet($seller->id, (mt_rand(0, 3) === 0 ? '-' : '') . Money::percentOf($amount, '5.00'), 'random', ledgerRef(LedgerReferenceType::ADJUSTMENT), $system),
            };
        } catch (DomainException) {
            // An overdraft refusal; the invariants must still hold.
        }
    }
}

it('keeps every invariant through hundreds of random postings', function (): void {
    postRandomFlows(300);

    $report = app(LedgerReconciler::class)->run();

    expect(LedgerTransaction::query()->count())->toBeGreaterThan(200)
        ->and($report->isClean())->toBeTrue()
        ->and($report->totalDebits)->toBe($report->totalCredits)
        ->and(Money::isPositive($report->totalDebits))->toBeTrue();

    foreach (LedgerAccount::query()->get() as $account) {
        $entries = LedgerEntry::query()->where('account_id', $account->id)->orderBy('created_at')->get();
        $sum = Money::ZERO;

        foreach ($entries as $entry) {
            $increase = $account->normal_side->value === 'debit' ? Money::subtract($entry->debit, $entry->credit) : Money::subtract($entry->credit, $entry->debit);
            $sum = Money::add($sum, $increase);
        }

        expect($account->balance)->toBe($sum, $account->code);

        if (! $account->type->mayGoNegative()) {
            expect(Money::isNegative($account->balance))->toBeFalse($account->code);
        }
    }

    expect(DB::table('ledger_entries')->whereRaw('(debit > 0) = (credit > 0)')->count())->toBe(0);
});

it('finds nothing to report on a consistent ledger and alerts nobody', function (): void {
    Notification::fake();
    postRandomFlows(30);

    (new ReconcileLedgerJob)->handle(app(LedgerReconciler::class), app(StaffDirectory::class));

    Notification::assertNothingSent();
});

it('detects an entry edited behind the ledger\'s back and alerts finance staff', function (): void {
    Notification::fake();
    $this->seed(RolesAndPermissionsSeeder::class);
    $finance = User::factory()->create();
    $finance->assignRole('super_admin');
    $moderator = User::factory()->create();
    $moderator->assignRole('moderator');

    $seller = User::factory()->create();
    app(LedgerRecipes::class)->chargeCommission(Order::factory()->make(['id' => (string) Str::ulid(), 'ad_id' => null, 'buyer_id' => null, 'seller_id' => $seller->id, 'commission_amount' => '12.50']), LedgerActor::system());

    DB::table('ledger_entries')->where('debit', '>', 0)->update(['debit' => '13.50']);

    $report = app(LedgerReconciler::class)->run();
    expect($report->isClean())->toBeFalse()
        ->and($report->trialBalanceHolds())->toBeFalse()
        ->and($report->unbalancedTransactionIds)->toHaveCount(1)
        ->and($report->accountMismatches)->toHaveCount(1)
        ->and($report->accountMismatches[0]['code'])->toBe("user:{$seller->id}:commission_receivable")
        ->and($report->accountMismatches[0]['stored'])->toBe('12.50')
        ->and($report->accountMismatches[0]['computed'])->toBe('13.50');

    dispatch_sync(new ReconcileLedgerJob);

    Notification::assertSentTo($finance, LedgerReconciliationFailedNotification::class, fn (LedgerReconciliationFailedNotification $alert): bool => $alert->accountMismatches === 1 && $alert->unbalancedTransactions === 1 && ! $alert->trialBalanceHolds);
    Notification::assertNotSentTo($moderator, LedgerReconciliationFailedNotification::class);
});

it('detects a cached balance that no longer matches its entries', function (): void {
    $seller = User::factory()->create();
    app(LedgerRecipes::class)->chargeCommission(Order::factory()->make(['id' => (string) Str::ulid(), 'ad_id' => null, 'buyer_id' => null, 'seller_id' => $seller->id, 'commission_amount' => '8.00']), LedgerActor::system());

    DB::table('ledger_accounts')->where('code', 'platform:revenue')->update(['balance' => '80.00']);

    $report = app(LedgerReconciler::class)->run();

    expect($report->trialBalanceHolds())->toBeTrue()
        ->and($report->unbalancedTransactionIds)->toBe([])
        ->and($report->accountMismatches)->toHaveCount(1)
        ->and($report->accountMismatches[0]['code'])->toBe('platform:revenue');
});

it('runs on the low queue once at a time', function (): void {
    $job = new ReconcileLedgerJob;

    expect($job->queue)->toBe('low')
        ->and($job)->toBeInstanceOf(ShouldBeUnique::class);
});
