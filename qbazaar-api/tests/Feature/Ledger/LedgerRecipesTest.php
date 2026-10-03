<?php

declare(strict_types=1);

use App\Data\Ledger\LedgerActor;
use App\Data\Ledger\LedgerReference;
use App\Enums\LedgerAccountType as Account;
use App\Enums\LedgerReferenceType;
use App\Enums\LedgerTransactionType as Flow;
use App\Exceptions\DomainException;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\Order;
use App\Models\User;
use App\Services\Ledger\LedgerAccounts;
use App\Services\Ledger\LedgerRecipes;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Each flow is checked against its row in qbazaar-contracts/LEDGER.md: the
 * exact accounts debited and credited, and the resulting balances.
 */
beforeEach(function (): void {
    $this->recipes = app(LedgerRecipes::class);
    $this->accounts = app(LedgerAccounts::class);
    $this->admin = LedgerActor::system();
    $this->seller = User::factory()->create();
    $this->order = Order::factory()->create([
        'ad_id' => null,
        'seller_id' => $this->seller->id,
        'total' => '210.00',
        'unit_price' => '200.00',
        'shipping_fee' => '10.00',
        'commission_rate' => '5.00',
        'commission_amount' => '10.00',
    ]);
});

function recipeReference(LedgerReferenceType $type): LedgerReference
{
    return new LedgerReference($type, (string) Str::ulid());
}

/**
 * @return list<string> "debit|credit account amount" per entry
 */
function recipeLines(LedgerTransaction $transaction): array
{
    return $transaction->entries()->with('account')->get()
        ->map(fn (LedgerEntry $entry): string => Str::of($entry->account->code)->replace($entry->account->owner_id ?? '', 'U')
            ->prepend(Money::isPositive($entry->debit) ? "Dr {$entry->debit} " : "Cr {$entry->credit} ")->toString())
        ->all();
}

function recipeBalance(Account $type, ?string $owner = null): string
{
    return app(LedgerAccounts::class)->balanceOf($type, $owner);
}

function fundRecipeWallet(User $user, string $amount): void
{
    app(LedgerRecipes::class)->adjustWallet($user->id, $amount, 'test funding', recipeReference(LedgerReferenceType::ADJUSTMENT), LedgerActor::system());
}

it('books the commission of a cash handover as the seller\'s debt', function (): void {
    $transaction = $this->recipes->chargeCommission($this->order, $this->admin);

    expect($transaction->type)->toBe(Flow::COMMISSION_CHARGED)
        ->and(recipeLines($transaction))->toEqualCanonicalizing(['Dr 10.00 user:U:commission_receivable', 'Cr 10.00 platform:revenue'])
        ->and(recipeBalance(Account::USER_COMMISSION_RECEIVABLE, $this->seller->id))->toBe('10.00')
        ->and(recipeBalance(Account::PLATFORM_REVENUE))->toBe('10.00');
});

it('posts nothing for a zero commission', function (): void {
    $this->order->forceFill(['commission_amount' => '0.00'])->save();

    expect($this->recipes->chargeCommission($this->order, $this->admin))->toBeNull()
        ->and(LedgerTransaction::query()->count())->toBe(0);
});

it('settles commission debt by bank transfer', function (): void {
    $this->recipes->chargeCommission($this->order, $this->admin);

    $transaction = $this->recipes->settleCommissionByBankTransfer($this->seller->id, '10.00', recipeReference(LedgerReferenceType::SETTLEMENT), $this->admin);

    expect(recipeLines($transaction))->toEqualCanonicalizing(['Dr 10.00 platform:bank', 'Cr 10.00 user:U:commission_receivable'])
        ->and(recipeBalance(Account::USER_COMMISSION_RECEIVABLE, $this->seller->id))->toBe('0.00')
        ->and(recipeBalance(Account::PLATFORM_BANK))->toBe('10.00');
});

it('nets commission debt against the seller\'s wallet', function (): void {
    fundRecipeWallet($this->seller, '50.00');
    $this->recipes->chargeCommission($this->order, $this->admin);

    $transaction = $this->recipes->settleCommissionFromWallet($this->seller->id, '10.00', recipeReference(LedgerReferenceType::SETTLEMENT), $this->admin);

    expect(recipeLines($transaction))->toEqualCanonicalizing(['Dr 10.00 user:U:wallet', 'Cr 10.00 user:U:commission_receivable'])
        ->and(recipeBalance(Account::USER_WALLET, $this->seller->id))->toBe('40.00')
        ->and(recipeBalance(Account::USER_COMMISSION_RECEIVABLE, $this->seller->id))->toBe('0.00');
});

it('holds an escrow payment and releases it to the seller minus the commission', function (): void {
    $received = $this->recipes->receiveOrderPayment($this->order, Account::PLATFORM_GATEWAY_CLEARING, $this->admin);

    expect(recipeLines($received))->toEqualCanonicalizing(['Dr 210.00 platform:gateway_clearing', 'Cr 210.00 platform:escrow'])
        ->and(recipeBalance(Account::PLATFORM_ESCROW))->toBe('210.00');

    $released = $this->recipes->releaseEscrow($this->order, $this->admin);

    expect(recipeLines($released))->toEqualCanonicalizing(['Dr 210.00 platform:escrow', 'Cr 200.00 user:U:wallet', 'Cr 10.00 platform:revenue'])
        ->and(recipeBalance(Account::PLATFORM_ESCROW))->toBe('0.00')
        ->and(recipeBalance(Account::USER_WALLET, $this->seller->id))->toBe('200.00')
        ->and(recipeBalance(Account::PLATFORM_REVENUE))->toBe('10.00');
});

it('refunds an escrow payment the way it came in', function (): void {
    $this->recipes->receiveOrderPayment($this->order, Account::PLATFORM_BANK, $this->admin);

    $refund = $this->recipes->refundOrderPayment($this->order, $this->admin);

    expect(recipeLines($refund))->toEqualCanonicalizing(['Dr 210.00 platform:escrow', 'Cr 210.00 platform:bank'])
        ->and(recipeBalance(Account::PLATFORM_ESCROW))->toBe('0.00')
        ->and(recipeBalance(Account::PLATFORM_BANK))->toBe('0.00');
});

it('refunds a card payment back through the gateway', function (): void {
    $this->recipes->receiveOrderPayment($this->order, Account::PLATFORM_GATEWAY_CLEARING, $this->admin);

    $refund = $this->recipes->refundOrderPayment($this->order, $this->admin);

    expect(recipeLines($refund))->toEqualCanonicalizing(['Dr 210.00 platform:escrow', 'Cr 210.00 platform:gateway_clearing'])
        ->and(recipeBalance(Account::PLATFORM_GATEWAY_CLEARING))->toBe('0.00');
});

it('releases or refunds an escrow only after the payment arrived', function (string $outcome): void {
    $this->recipes->{$outcome}($this->order, $this->admin);
})->with(['releaseEscrow', 'refundOrderPayment'])->throws(LogicException::class, 'No payment was received');

it('never both releases and refunds one escrow, even when other escrows could cover it', function (): void {
    $other = Order::factory()->create(['ad_id' => null, 'seller_id' => $this->seller->id, 'total' => '500.00', 'commission_amount' => '25.00']);
    $this->recipes->receiveOrderPayment($other, Account::PLATFORM_BANK, $this->admin);
    $this->recipes->receiveOrderPayment($this->order, Account::PLATFORM_BANK, $this->admin);
    $released = $this->recipes->releaseEscrow($this->order, $this->admin);

    expect(fn () => $this->recipes->refundOrderPayment($this->order, $this->admin))->toThrow(LogicException::class)
        ->and($this->recipes->releaseEscrow($this->order, $this->admin)->id)->toBe($released->id)
        ->and(recipeBalance(Account::PLATFORM_ESCROW))->toBe('500.00')
        ->and(recipeBalance(Account::USER_WALLET, $this->seller->id))->toBe('200.00');
});

it('collects escrow payments only into the bank or gateway clearing', function (): void {
    $this->recipes->receiveOrderPayment($this->order, Account::PLATFORM_REVENUE, $this->admin);
})->throws(InvalidArgumentException::class);

it('takes a withdrawal out of the wallet at request time and pays it from the bank', function (): void {
    fundRecipeWallet($this->seller, '100.00');
    $withdrawal = recipeReference(LedgerReferenceType::WITHDRAWAL);

    $requested = $this->recipes->requestWithdrawal($this->seller->id, '60.00', $withdrawal, LedgerActor::user($this->seller));
    expect(recipeLines($requested))->toEqualCanonicalizing(['Dr 60.00 user:U:wallet', 'Cr 60.00 platform:payouts_payable'])
        ->and(recipeBalance(Account::USER_WALLET, $this->seller->id))->toBe('40.00');

    $paid = $this->recipes->payWithdrawal($withdrawal, $this->admin);
    expect(recipeLines($paid))->toEqualCanonicalizing(['Dr 60.00 platform:payouts_payable', 'Cr 60.00 platform:bank'])
        ->and(recipeBalance(Account::PLATFORM_PAYOUTS_PAYABLE))->toBe('0.00');
});

it('returns a rejected withdrawal to the wallet', function (): void {
    fundRecipeWallet($this->seller, '100.00');
    $withdrawal = recipeReference(LedgerReferenceType::WITHDRAWAL);
    $requested = $this->recipes->requestWithdrawal($this->seller->id, '60.00', $withdrawal, LedgerActor::user($this->seller));

    $rejected = $this->recipes->rejectWithdrawal($withdrawal, $this->admin, 'IBAN does not match the account name');

    expect($rejected->type)->toBe(Flow::WITHDRAWAL_REJECTED)
        ->and($rejected->reversal_of)->toBe($requested->id)
        ->and(recipeLines($rejected))->toEqualCanonicalizing(['Cr 60.00 user:U:wallet', 'Dr 60.00 platform:payouts_payable'])
        ->and(recipeBalance(Account::USER_WALLET, $this->seller->id))->toBe('100.00')
        ->and(recipeBalance(Account::PLATFORM_PAYOUTS_PAYABLE))->toBe('0.00');
});

it('never both pays and rejects one withdrawal, even when other withdrawals are pending', function (string $first, string $second): void {
    fundRecipeWallet($this->seller, '100.00');
    $this->recipes->requestWithdrawal($this->seller->id, '40.00', recipeReference(LedgerReferenceType::WITHDRAWAL), LedgerActor::user($this->seller));
    $withdrawal = recipeReference(LedgerReferenceType::WITHDRAWAL);
    $this->recipes->requestWithdrawal($this->seller->id, '60.00', $withdrawal, LedgerActor::user($this->seller));

    $this->recipes->{$first}($withdrawal, $this->admin);
    $wallet = recipeBalance(Account::USER_WALLET, $this->seller->id);

    expect(fn () => $this->recipes->{$second}($withdrawal, $this->admin))->toThrow(LogicException::class)
        ->and(recipeBalance(Account::USER_WALLET, $this->seller->id))->toBe($wallet)
        ->and(recipeBalance(Account::PLATFORM_PAYOUTS_PAYABLE))->toBe('40.00');
})->with([
    'paid, then rejected' => ['payWithdrawal', 'rejectWithdrawal'],
    'rejected, then paid' => ['rejectWithdrawal', 'payWithdrawal'],
]);

it('pays nothing for a withdrawal that was never requested', function (): void {
    $this->recipes->payWithdrawal(recipeReference(LedgerReferenceType::WITHDRAWAL), $this->admin);
})->throws(LogicException::class, 'was never requested');

it('refuses a withdrawal larger than the wallet', function (): void {
    fundRecipeWallet($this->seller, '10.00');

    $this->recipes->requestWithdrawal($this->seller->id, '10.01', recipeReference(LedgerReferenceType::WITHDRAWAL), LedgerActor::user($this->seller));
})->throws(DomainException::class);

it('sells a promotion from the wallet or by bank transfer', function (): void {
    fundRecipeWallet($this->seller, '30.00');

    $fromWallet = $this->recipes->purchasePromotionFromWallet($this->seller->id, '25.00', recipeReference(LedgerReferenceType::PROMOTION), LedgerActor::user($this->seller));
    $byTransfer = $this->recipes->purchasePromotionByBankTransfer('40.00', recipeReference(LedgerReferenceType::PROMOTION), $this->admin);

    expect(recipeLines($fromWallet))->toEqualCanonicalizing(['Dr 25.00 user:U:wallet', 'Cr 25.00 platform:revenue'])
        ->and(recipeLines($byTransfer))->toEqualCanonicalizing(['Dr 40.00 platform:bank', 'Cr 40.00 platform:revenue'])
        ->and(recipeBalance(Account::PLATFORM_REVENUE))->toBe('65.00')
        ->and(recipeBalance(Account::USER_WALLET, $this->seller->id))->toBe('5.00');
});

it('adjusts a wallet both ways against the adjustments account', function (): void {
    fundRecipeWallet($this->seller, '20.00');
    $this->recipes->adjustWallet($this->seller->id, '-5.00', 'duplicate credit', recipeReference(LedgerReferenceType::ADJUSTMENT), $this->admin);

    expect(recipeBalance(Account::USER_WALLET, $this->seller->id))->toBe('15.00')
        ->and(recipeBalance(Account::PLATFORM_ADJUSTMENTS))->toBe('15.00');
});

it('runs each flow once per business record', function (): void {
    $first = $this->recipes->chargeCommission($this->order, $this->admin);
    $second = $this->recipes->chargeCommission($this->order, $this->admin);

    expect($second?->id)->toBe($first?->id)
        ->and(recipeBalance(Account::USER_COMMISSION_RECEIVABLE, $this->seller->id))->toBe('10.00');
});
