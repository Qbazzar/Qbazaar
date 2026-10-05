<?php

declare(strict_types=1);

namespace App\Services\Ledger;

use App\Data\Ledger\LedgerLine;
use App\Enums\LedgerAccountType;
use App\Enums\LedgerOwnerType;
use App\Models\LedgerAccount;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Finds ledger accounts, opening them the first time they are needed.
 * Opening is idempotent: the unique `code` turns a concurrent second insert
 * into a no-op.
 */
class LedgerAccounts
{
    /**
     * @param list<LedgerLine> $lines
     * @return array<string, string> account id keyed by account code
     */
    public function idsFor(array $lines): array
    {
        $wanted = [];

        foreach ($lines as $line) {
            $wanted[$line->accountCode()] = $line;
        }

        $ids = $this->idsByCode(array_keys($wanted));
        $missing = array_diff_key($wanted, $ids);

        if ($missing === []) {
            return $ids;
        }

        $this->open(array_values($missing));

        return $this->idsByCode(array_keys($wanted));
    }

    /**
     * The ids of the accounts that already exist, without opening any.
     *
     * @param list<LedgerLine> $lines
     * @return array<string, string> account id keyed by account code
     */
    public function existingIdsFor(array $lines): array
    {
        return $this->idsByCode(array_values(array_unique(array_map(fn (LedgerLine $line): string => $line->accountCode(), $lines))));
    }

    /**
     * The user's accounts that exist so far, keyed by type.
     *
     * @return Collection<string, LedgerAccount>
     */
    public function ofUser(string $userId): Collection
    {
        return LedgerAccount::query()
            ->where('owner_type', LedgerOwnerType::USER->value)
            ->where('owner_id', $userId)
            ->get()
            ->keyBy(fn (LedgerAccount $account): string => $account->type->value);
    }

    /**
     * Locks the user's existing accounts, and optionally one platform
     * account the caller is about to post to, in a single id-ordered
     * statement (the ledger's lock order). Taking the platform account in
     * the same pass keeps the later posting from locking a lower id while
     * this transaction already holds a higher one.
     *
     * @return Collection<string, LedgerAccount> keyed by account type
     */
    public function lockUserAccounts(string $userId, ?LedgerAccountType $alongside = null): Collection
    {
        return LedgerAccount::query()
            ->where(fn ($query) => $query
                ->where(fn ($owned) => $owned->where('owner_type', LedgerOwnerType::USER->value)->where('owner_id', $userId))
                ->when($alongside !== null, fn ($query) => $query->orWhere('code', $alongside?->code())))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (LedgerAccount $account): string => $account->type->value);
    }

    public function balanceOf(LedgerAccountType $type, ?string $ownerId = null): string
    {
        $balance = LedgerAccount::query()->where('code', $type->code($ownerId))->value('balance');

        return $balance === null ? Money::ZERO : Money::round((string) $balance);
    }

    /**
     * @param list<string> $codes
     * @return array<string, string>
     */
    private function idsByCode(array $codes): array
    {
        /** @var array<string, string> */
        return LedgerAccount::query()->whereIn('code', $codes)->pluck('id', 'code')->all();
    }

    /**
     * @param list<LedgerLine> $lines
     */
    private function open(array $lines): void
    {
        $now = now();

        $rows = array_map(fn (LedgerLine $line): array => [
            'id' => (string) Str::ulid(),
            'code' => $line->accountCode(),
            'owner_type' => $line->accountType->ownerType()->value,
            'owner_id' => $line->ownerId,
            'type' => $line->accountType->value,
            'normal_side' => $line->accountType->normalSide()->value,
            'currency' => 'QAR',
            'balance' => Money::ZERO,
            'version' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ], $lines);

        LedgerAccount::query()->insertOrIgnore($rows);
    }
}
