<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PurchaseRequest;
use App\Models\User;

/**
 * The buyer edits or cancels their request, the seller accepts or rejects
 * it. Whether it is still pending is checked under the lock in the actions.
 */
class PurchaseRequestPolicy
{
    public function update(User $user, PurchaseRequest $request): bool
    {
        return $user->id === $request->buyer_id;
    }

    public function cancel(User $user, PurchaseRequest $request): bool
    {
        return $user->id === $request->buyer_id;
    }

    public function accept(User $user, PurchaseRequest $request): bool
    {
        return $user->id === $request->seller_id;
    }

    public function reject(User $user, PurchaseRequest $request): bool
    {
        return $user->id === $request->seller_id;
    }
}
