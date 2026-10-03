<?php

declare(strict_types=1);

namespace App\Services\Ledger;

use App\Data\Ledger\LedgerActor;
use App\Data\Ledger\LedgerLine;
use App\Data\Ledger\LedgerReference;
use App\Enums\LedgerAccountType;
use App\Enums\LedgerSide;
use App\Enums\LedgerTransactionType;
use App\Exceptions\DomainException;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

/**
 * The only writer of the ledger.
 *
 * A posting is one database transaction: the journal row, its entries and
 * the cached balances of every touched account commit together or not at
 * all. The journal row is inserted first, so its unique idempotency key
 * makes a concurrent duplicate wait and then fail instead of posting twice;
 * the touched accounts are then locked in id order, which keeps two
 * postings over the same accounts from deadlocking.
 */
class LedgerService
{
    public function __construct(
        private readonly LedgerAccounts $accounts,
    ) {}

    /**
     * Posts a balanced transaction, or returns the one already posted under
     * the same idempotency key.
     *
     * @param list<LedgerLine> $lines
     */
    public function post(
        LedgerTransactionType $type,
        array $lines,
        string $idempotencyKey,
        ?LedgerReference $reference,
        LedgerActor $actor,
        ?string $memo = null,
        ?LedgerTransaction $reversalOf = null,
    ): LedgerTransaction {
        $this->assertBalanced($lines);

        $existing = $this->findByKey($idempotencyKey);

        if ($existing !== null) {
            return $this->assertSamePosting($existing, $type, $lines);
        }

        $accountIds = $this->accounts->idsFor($lines);

        try {
            return DB::transaction(fn (): LedgerTransaction => $this->write(
                $type,
                $lines,
                $accountIds,
                $idempotencyKey,
                $reference,
                $actor,
                $memo,
                $reversalOf,
            ));
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $this->findByKey($idempotencyKey, locking: true);

            if ($existing === null) {
                throw $exception;
            }

            return $this->assertSamePosting($existing, $type, $lines);
        }
    }

    /**
     * Posts the mirror image of a transaction, which cancels its effect on
     * every account while keeping both in the history.
     */
    public function reverse(
        LedgerTransaction $original,
        string $idempotencyKey,
        LedgerActor $actor,
        ?string $memo = null,
        LedgerTransactionType $type = LedgerTransactionType::REVERSAL,
    ): LedgerTransaction {
        if ($original->reversal_of !== null) {
            throw new LogicException('A reversal cannot be reversed; post a new transaction instead.');
        }

        $original->loadMissing('entries.account');

        $lines = array_values($original->entries
            ->map(fn (LedgerEntry $entry): LedgerLine => $this->lineOf($entry)->opposite())
            ->all());

        $reference = $original->reference_type === null || $original->reference_id === null
            ? null
            : new LedgerReference($original->reference_type, $original->reference_id);

        return $this->post($type, $lines, $idempotencyKey, $reference, $actor, $memo, $original);
    }

    public function findByKey(string $idempotencyKey, bool $locking = false): ?LedgerTransaction
    {
        return LedgerTransaction::query()
            ->where('idempotency_key', $idempotencyKey)
            // A locking read sees a row a concurrent transaction just committed.
            ->when($locking, fn ($query) => $query->sharedLock())
            ->with('entries')
            ->first();
    }

    /**
     * @param list<LedgerLine> $lines
     * @param array<string, string> $accountIds
     */
    private function write(
        LedgerTransactionType $type,
        array $lines,
        array $accountIds,
        string $idempotencyKey,
        ?LedgerReference $reference,
        LedgerActor $actor,
        ?string $memo,
        ?LedgerTransaction $reversalOf,
    ): LedgerTransaction {
        $now = now();

        $transaction = new LedgerTransaction;
        $transaction->forceFill([
            'type' => $type,
            'idempotency_key' => $idempotencyKey,
            'reference_type' => $reference?->type,
            'reference_id' => $reference?->id,
            'memo' => $memo === null ? null : Str::limit($memo, 250),
            'created_by_type' => $actor->type,
            'created_by_id' => $actor->id,
            'reversal_of' => $reversalOf?->id,
            'posted_at' => $now,
        ])->save();

        $locked = $this->lockAccounts(array_values($accountIds));
        $balances = array_map(fn (LedgerAccount $account): string => $account->balance, $locked);
        $entries = [];

        foreach ($lines as $line) {
            $account = $locked[$accountIds[$line->accountCode()]];
            $balances[$account->id] = $this->applyLine($balances[$account->id], $account, $line);

            $entries[] = [
                'id' => (string) Str::ulid(),
                'transaction_id' => $transaction->id,
                'account_id' => $account->id,
                'debit' => $line->side === LedgerSide::DEBIT ? $line->amount : Money::ZERO,
                'credit' => $line->side === LedgerSide::CREDIT ? $line->amount : Money::ZERO,
                'balance_after' => $balances[$account->id],
                'created_at' => $now,
            ];
        }

        foreach ($balances as $accountId => $balance) {
            $this->storeBalance($locked[$accountId], $balance);
        }

        LedgerEntry::query()->insert($entries);

        return $transaction->load('entries');
    }

    /**
     * @param list<string> $accountIds
     * @return array<string, LedgerAccount> keyed by account id
     */
    private function lockAccounts(array $accountIds): array
    {
        return LedgerAccount::query()
            ->whereKey($accountIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id')
            ->all();
    }

    private function applyLine(string $balance, LedgerAccount $account, LedgerLine $line): string
    {
        return $line->side === $account->normal_side
            ? Money::add($balance, $line->amount)
            : Money::subtract($balance, $line->amount);
    }

    private function storeBalance(LedgerAccount $account, string $balance): void
    {
        if (Money::isNegative($balance) && ! $account->type->mayGoNegative()) {
            $this->refuseOverdraft($account->type, $balance);
        }

        LedgerAccount::query()->whereKey($account->id)->update([
            'balance' => $balance,
            'version' => DB::raw('version + 1'),
        ]);
    }

    private function refuseOverdraft(LedgerAccountType $type, string $balance): never
    {
        $error = $type->overdraftError();

        if ($error === null) {
            throw new LogicException("Posting would leave [{$type->value}] at {$balance}.");
        }

        throw new DomainException($error);
    }

    /**
     * @param list<LedgerLine> $lines
     */
    private function assertBalanced(array $lines): void
    {
        if (count($lines) < 2) {
            throw new InvalidArgumentException('A transaction needs at least two lines.');
        }

        $debits = Money::add(...array_map(fn (LedgerLine $line): string => $line->side === LedgerSide::DEBIT ? $line->amount : Money::ZERO, $lines));
        $credits = Money::add(...array_map(fn (LedgerLine $line): string => $line->side === LedgerSide::CREDIT ? $line->amount : Money::ZERO, $lines));

        if (Money::compare($debits, $credits) !== 0) {
            throw new InvalidArgumentException("Unbalanced transaction: debits {$debits}, credits {$credits}.");
        }
    }

    /**
     * A replay must be the same posting. A key reused for other accounts or
     * amounts is a caller bug that would otherwise be silently swallowed.
     *
     * @param list<LedgerLine> $lines
     */
    private function assertSamePosting(LedgerTransaction $existing, LedgerTransactionType $type, array $lines): LedgerTransaction
    {
        if ($existing->type !== $type) {
            throw new LogicException("Idempotency key [{$existing->idempotency_key}] already belongs to a {$existing->type->value} transaction.");
        }

        $accountIds = $this->accounts->existingIdsFor($lines);

        $requested = array_map(fn (LedgerLine $line): string => implode('|', [
            $accountIds[$line->accountCode()] ?? '',
            $line->side === LedgerSide::DEBIT ? $line->amount : Money::ZERO,
            $line->side === LedgerSide::CREDIT ? $line->amount : Money::ZERO,
        ]), $lines);

        $posted = $existing->entries
            ->map(fn (LedgerEntry $entry): string => implode('|', [$entry->account_id, $entry->debit, $entry->credit]))
            ->all();

        sort($requested);
        sort($posted);

        if ($requested !== $posted) {
            throw new LogicException("Idempotency key [{$existing->idempotency_key}] was already used for a different posting.");
        }

        return $existing;
    }

    private function lineOf(LedgerEntry $entry): LedgerLine
    {
        $type = $entry->account->type;

        return Money::isPositive($entry->debit)
            ? LedgerLine::debit($type, $entry->debit, $entry->account->owner_id)
            : LedgerLine::credit($type, $entry->credit, $entry->account->owner_id);
    }
}
