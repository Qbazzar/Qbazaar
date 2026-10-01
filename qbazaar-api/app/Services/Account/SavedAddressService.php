<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Support\Facades\DB;

/**
 * Owns the address book rules: at most `qbazaar.account.max_addresses`
 * addresses and exactly one default while any exist.
 *
 * Every write locks the owner's user row first, so two concurrent requests
 * can neither both pass the limit check nor both end up as the default.
 */
class SavedAddressService
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function create(User $user, array $attributes, bool $makeDefault): UserAddress
    {
        return DB::transaction(function () use ($user, $attributes, $makeDefault): UserAddress {
            $this->lockOwner($user);

            $count = UserAddress::query()->ownedBy($user)->count();

            if ($count >= (int) config('qbazaar.account.max_addresses')) {
                throw new DomainException(ErrorCode::ADDRESS_LIMIT_REACHED);
            }

            $address = new UserAddress($attributes);
            $address->user_id = $user->id;
            $address->is_default = false;
            $address->save();

            if ($makeDefault || $count === 0) {
                $this->makeDefault($user, $address);
            }

            return $address;
        });
    }

    /**
     * Clearing `is_default` on the current default is ignored: the default
     * only moves when another address is made the default.
     *
     * @param array<string, mixed> $attributes
     */
    public function update(User $user, string $addressId, array $attributes, bool $makeDefault): UserAddress
    {
        return DB::transaction(function () use ($user, $addressId, $attributes, $makeDefault): UserAddress {
            $this->lockOwner($user);

            $address = $this->findOwned($user, $addressId);
            $address->fill($attributes)->save();

            if ($makeDefault && ! $address->is_default) {
                $this->makeDefault($user, $address);
            }

            return $address;
        });
    }

    public function delete(User $user, string $addressId): void
    {
        DB::transaction(function () use ($user, $addressId): void {
            $this->lockOwner($user);

            $address = $this->findOwned($user, $addressId);
            $address->delete();

            if (! $address->is_default) {
                return;
            }

            $successor = UserAddress::query()->ownedBy($user)->latest()->latest('id')->first();

            if ($successor !== null) {
                $this->makeDefault($user, $successor);
            }
        });
    }

    private function makeDefault(User $user, UserAddress $address): void
    {
        UserAddress::query()
            ->ownedBy($user)
            ->where('is_default', true)
            ->whereKeyNot($address->id)
            ->update(['is_default' => false]);

        $address->is_default = true;
        $address->save();
    }

    private function findOwned(User $user, string $addressId): UserAddress
    {
        $address = UserAddress::query()->ownedBy($user)->whereKey($addressId)->first();

        if ($address === null) {
            throw new DomainException(ErrorCode::ADDRESS_NOT_FOUND);
        }

        return $address;
    }

    private function lockOwner(User $user): void
    {
        User::query()->whereKey($user->id)->lockForUpdate()->value('id');
    }
}
