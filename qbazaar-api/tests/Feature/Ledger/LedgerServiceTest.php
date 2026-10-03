<?php

declare(strict_types=1);

use App\Data\Ledger\LedgerActor;
use App\Data\Ledger\LedgerLine;
use App\Data\Ledger\LedgerReference;
use App\Enums\LedgerAccountType as Account;
use App\Enums\LedgerReferenceType;
use App\Enums\LedgerTransactionType as Flow;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Services\Ledger\LedgerAccounts;
use App\Services\Ledger\LedgerService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->ledger = app(LedgerService::class);
    $this->balances = app(LedgerAccounts::class);
    $this->userId = (string) Str::ulid();
});

function commissionLines(string $userId, string $amount = '25.00'): array
{
    return [
        LedgerLine::debit(Account::USER_COMMISSION_RECEIVABLE, $amount, $userId),
        LedgerLine::credit(Account::PLATFORM_REVENUE, $amount),
    ];
}

function postCommission(string $userId, string $key = 'test:commission', string $amount = '25.00'): LedgerTransaction
{
    return app(LedgerService::class)->post(Flow::COMMISSION_CHARGED, commissionLines($userId, $amount), $key, null, LedgerActor::system());
}

it('posts a balanced transaction and moves each balance in its normal direction', function (): void {
    $transaction = postCommission($this->userId);

    expect($transaction->entries)->toHaveCount(2)
        ->and($this->balances->balanceOf(Account::USER_COMMISSION_RECEIVABLE, $this->userId))->toBe('25.00')
        ->and($this->balances->balanceOf(Account::PLATFORM_REVENUE))->toBe('25.00');

    $entry = LedgerEntry::query()->where('debit', '>', 0)->sole();
    expect($entry->debit)->toBe('25.00')
        ->and($entry->credit)->toBe('0.00')
        ->and($entry->balance_after)->toBe('25.00');
});

it('opens accounts lazily, once, with a stable code', function (): void {
    postCommission($this->userId, 'first');
    postCommission($this->userId, 'second');

    expect(LedgerAccount::query()->count())->toBe(2)
        ->and(LedgerAccount::query()->pluck('code')->all())->toEqualCanonicalizing([
            "user:{$this->userId}:commission_receivable",
            'platform:revenue',
        ])
        ->and(LedgerAccount::query()->where('code', 'platform:revenue')->value('version'))->toBe(2);
});

it('refuses a transaction whose debits and credits differ', function (): void {
    $this->ledger->post(Flow::ADJUSTMENT, [
        LedgerLine::debit(Account::PLATFORM_ADJUSTMENTS, '10.00'),
        LedgerLine::credit(Account::USER_WALLET, '9.99', $this->userId),
    ], 'unbalanced', null, LedgerActor::system());
})->throws(InvalidArgumentException::class, 'Unbalanced');

it('refuses a single-line transaction', function (): void {
    $this->ledger->post(Flow::ADJUSTMENT, [LedgerLine::debit(Account::PLATFORM_BANK, '1.00')], 'single', null, LedgerActor::system());
})->throws(InvalidArgumentException::class);

it('refuses zero, negative and sub-cent amounts', function (string $amount): void {
    LedgerLine::debit(Account::PLATFORM_BANK, $amount);
})->with(['0.00', '-5.00', '0.001'])->throws(InvalidArgumentException::class);

it('refuses a user account without an owner and a platform account with one', function (): void {
    expect(fn () => LedgerLine::debit(Account::USER_WALLET, '1.00'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => LedgerLine::debit(Account::PLATFORM_BANK, '1.00', $this->userId))->toThrow(InvalidArgumentException::class);
});

it('returns the first transaction when the same idempotency key is posted again', function (): void {
    $first = postCommission($this->userId, 'order:x:commission_charged');
    $again = postCommission($this->userId, 'order:x:commission_charged');

    expect($again->id)->toBe($first->id)
        ->and(LedgerTransaction::query()->count())->toBe(1)
        ->and(LedgerEntry::query()->count())->toBe(2)
        ->and($this->balances->balanceOf(Account::PLATFORM_REVENUE))->toBe('25.00');
});

it('refuses to reuse an idempotency key for another kind of transaction', function (): void {
    postCommission($this->userId, 'shared-key');

    $this->ledger->post(Flow::PROMOTION_PURCHASED, [
        LedgerLine::debit(Account::PLATFORM_BANK, '25.00'),
        LedgerLine::credit(Account::PLATFORM_REVENUE, '25.00'),
    ], 'shared-key', null, LedgerActor::system());
})->throws(LogicException::class);

it('enforces the idempotency key in the database', function (): void {
    postCommission($this->userId, 'unique-key');

    $duplicate = new LedgerTransaction;
    $duplicate->forceFill([
        'type' => Flow::COMMISSION_CHARGED,
        'idempotency_key' => 'unique-key',
        'created_by_type' => 'system',
        'posted_at' => now(),
    ])->save();
})->throws(UniqueConstraintViolationException::class);

it('never lets a wallet go below zero and leaves no trace of the refused posting', function (): void {
    try {
        $this->ledger->post(Flow::PROMOTION_PURCHASED, [
            LedgerLine::debit(Account::USER_WALLET, '1.00', $this->userId),
            LedgerLine::credit(Account::PLATFORM_REVENUE, '1.00'),
        ], 'overdraw', null, LedgerActor::system());
        $this->fail('The overdraft was accepted.');
    } catch (DomainException $exception) {
        expect($exception->errorCode)->toBe(ErrorCode::WALLET_INSUFFICIENT_BALANCE);
    }

    expect(LedgerTransaction::query()->count())->toBe(0)
        ->and(LedgerEntry::query()->count())->toBe(0)
        ->and($this->balances->balanceOf(Account::PLATFORM_REVENUE))->toBe('0.00');
});

it('refuses paying off more commission than is owed', function (): void {
    postCommission($this->userId, 'debt', '10.00');

    try {
        $this->ledger->post(Flow::COMMISSION_SETTLED, [
            LedgerLine::debit(Account::PLATFORM_BANK, '10.01'),
            LedgerLine::credit(Account::USER_COMMISSION_RECEIVABLE, '10.01', $this->userId),
        ], 'overpay', null, LedgerActor::system());
        $this->fail('The overpayment was accepted.');
    } catch (DomainException $exception) {
        expect($exception->errorCode)->toBe(ErrorCode::WALLET_EXCEEDS_COMMISSION_DEBT);
    }

    expect($this->balances->balanceOf(Account::USER_COMMISSION_RECEIVABLE, $this->userId))->toBe('10.00');
});

it('treats a negative platform escrow as a bug, not a user error', function (): void {
    $this->ledger->post(Flow::REFUND, [
        LedgerLine::debit(Account::PLATFORM_ESCROW, '5.00'),
        LedgerLine::credit(Account::PLATFORM_GATEWAY_CLEARING, '5.00'),
    ], 'bad-refund', null, LedgerActor::system());
})->throws(LogicException::class);

it('keeps journal rows and entries append-only', function (): void {
    $transaction = postCommission($this->userId);
    $entry = $transaction->entries->first();
    $account = LedgerAccount::query()->firstOrFail();

    expect(fn () => $transaction->forceFill(['memo' => 'edited'])->save())->toThrow(LogicException::class)
        ->and(fn () => $transaction->delete())->toThrow(LogicException::class)
        ->and(fn () => $entry->forceFill(['debit' => '1.00'])->save())->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class)
        ->and(fn () => $account->forceFill(['balance' => '999.00'])->save())->toThrow(LogicException::class)
        ->and(fn () => $account->delete())->toThrow(LogicException::class);

    expect(LedgerEntry::query()->count())->toBe(2)
        ->and($this->balances->balanceOf(Account::PLATFORM_REVENUE))->toBe('25.00');
});

it('corrects a posting with a reversal that keeps both in the history', function (): void {
    $original = postCommission($this->userId);

    $reversal = $this->ledger->reverse($original, 'fix:1', LedgerActor::system(), 'Charged by mistake');

    expect($reversal->type)->toBe(Flow::REVERSAL)
        ->and($reversal->reversal_of)->toBe($original->id)
        ->and($reversal->memo)->toBe('Charged by mistake')
        ->and($this->balances->balanceOf(Account::PLATFORM_REVENUE))->toBe('0.00')
        ->and($this->balances->balanceOf(Account::USER_COMMISSION_RECEIVABLE, $this->userId))->toBe('0.00')
        ->and(LedgerEntry::query()->count())->toBe(4)
        ->and($this->ledger->reverse($original, 'fix:1', LedgerActor::system())->id)->toBe($reversal->id);
});

it('reverses a transaction only once', function (): void {
    $original = postCommission($this->userId);
    $this->ledger->reverse($original, 'fix:1', LedgerActor::system());

    $this->ledger->reverse($original, 'fix:2', LedgerActor::system());
})->throws(UniqueConstraintViolationException::class);

it('does not reverse a reversal', function (): void {
    $reversal = $this->ledger->reverse(postCommission($this->userId), 'fix:1', LedgerActor::system());

    $this->ledger->reverse($reversal, 'fix:again', LedgerActor::system());
})->throws(LogicException::class);

it('records the reference and who posted', function (): void {
    $reference = new LedgerReference(LedgerReferenceType::SETTLEMENT, (string) Str::ulid());

    $transaction = $this->ledger->post(Flow::COMMISSION_SETTLED, [
        LedgerLine::debit(Account::PLATFORM_BANK, '5.00'),
        LedgerLine::credit(Account::PLATFORM_REVENUE, '5.00'),
    ], $reference->keyFor(Flow::COMMISSION_SETTLED), $reference, LedgerActor::system());

    expect($transaction->idempotency_key)->toBe("settlement:{$reference->id}:commission_settled")
        ->and($transaction->reference_type)->toBe(LedgerReferenceType::SETTLEMENT)
        ->and($transaction->reference_id)->toBe($reference->id)
        ->and($transaction->created_by_type->value)->toBe('system');
});
