<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\BankAccount;
use App\Models\User;
use App\Support\Iban;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Owns a seller's payout accounts: at most `qbazaar.wallet.max_bank_accounts`,
 * no IBAN twice, and exactly one default while any exist.
 *
 * Every write locks the owner's user row first, so two concurrent requests
 * can neither both pass the limit nor both end up as the default.
 */
class BankAccountService
{
    /**
     * @return Collection<int, BankAccount>
     */
    public function list(User $user): Collection
    {
        return BankAccount::query()
            ->ownedBy($user)
            ->orderByDesc('is_default')
            ->latest()
            ->limit((int) config('qbazaar.wallet.max_bank_accounts'))
            ->get();
    }

    public function create(User $user, string $holderName, string $iban, ?string $bankName, bool $makeDefault): BankAccount
    {
        $iban = Iban::normalize($iban);

        try {
            return DB::transaction(function () use ($user, $holderName, $iban, $bankName, $makeDefault): BankAccount {
                $this->lockOwner($user);

                $count = BankAccount::query()->ownedBy($user)->count();

                if ($count >= (int) config('qbazaar.wallet.max_bank_accounts')) {
                    throw new DomainException(ErrorCode::BANK_ACCOUNT_LIMIT_REACHED);
                }

                $account = new BankAccount;
                $account->forceFill([
                    'user_id' => $user->id,
                    'holder_name' => $holderName,
                    'iban' => $iban,
                    'iban_hash' => $this->hash($iban),
                    'iban_last4' => Iban::lastFour($iban),
                    'bank_name' => $bankName,
                    'is_default' => false,
                ])->save();

                if ($makeDefault || $count === 0) {
                    $this->makeDefault($user, $account);
                }

                return $account;
            });
        } catch (UniqueConstraintViolationException) {
            throw new DomainException(ErrorCode::BANK_ACCOUNT_DUPLICATE);
        }
    }

    public function delete(User $user, string $accountId): void
    {
        DB::transaction(function () use ($user, $accountId): void {
            $this->lockOwner($user);

            $account = $this->findOwned($user, $accountId);
            $account->delete();

            if (! $account->is_default) {
                return;
            }

            $successor = BankAccount::query()->ownedBy($user)->latest()->latest('id')->first();

            if ($successor !== null) {
                $this->makeDefault($user, $successor);
            }
        });
    }

    /**
     * The account a withdrawal pays into: the one named, or the default.
     */
    public function payoutAccount(User $user, ?string $accountId): BankAccount
    {
        if ($accountId !== null) {
            return $this->findOwned($user, $accountId);
        }

        return BankAccount::query()->ownedBy($user)->where('is_default', true)->first()
            ?? throw new DomainException(ErrorCode::WITHDRAWAL_BANK_ACCOUNT_REQUIRED);
    }

    public function findOwned(User $user, string $accountId): BankAccount
    {
        return BankAccount::query()->ownedBy($user)->whereKey($accountId)->first()
            ?? throw new DomainException(ErrorCode::BANK_ACCOUNT_NOT_FOUND);
    }

    private function makeDefault(User $user, BankAccount $account): void
    {
        BankAccount::query()
            ->ownedBy($user)
            ->where('is_default', true)
            ->whereKeyNot($account->id)
            ->update(['is_default' => false]);

        $account->forceFill(['is_default' => true])->save();
    }

    /**
     * Keyed with the app key, so the column alone cannot be brute-forced
     * back into IBANs the way a plain hash of a short number could.
     */
    private function hash(string $iban): string
    {
        return hash_hmac('sha256', $iban, (string) config('app.key'));
    }

    private function lockOwner(User $user): void
    {
        User::query()->whereKey($user->id)->lockForUpdate()->value('id');
    }
}
