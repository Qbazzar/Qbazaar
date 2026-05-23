<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Authorization rules for the signed-in user's own account.
 *
 * Every account endpoint operates on a single resource — "the caller's own
 * account" — so the policy ultimately reduces to "are you the owner?". We
 * still ship it as a proper Policy so:
 *   - controllers stay declarative (`$this->authorize('manage', $user)`),
 *   - the same rule can be reused by Filament / future admin actions,
 *   - sensitive ops (deactivate, delete-request, data export) refuse to run
 *     against a different user even if a misconfigured route ever forwards
 *     a stale `User` instance.
 */
class AccountPolicy
{
    public function view(User $user, User $account): bool
    {
        return $user->id === $account->id;
    }

    public function update(User $user, User $account): bool
    {
        return $user->id === $account->id;
    }

    /**
     * Soft-deactivate. The signed-in caller must own the account; we don't
     * let one user deactivate another's account through this endpoint.
     */
    public function deactivate(User $user, User $account): bool
    {
        return $user->id === $account->id;
    }

    /**
     * Schedule a hard delete. Owner-only.
     */
    public function requestDeletion(User $user, User $account): bool
    {
        return $user->id === $account->id;
    }

    /**
     * Export personal data — GDPR-style. Owner-only.
     */
    public function exportData(User $user, User $account): bool
    {
        return $user->id === $account->id;
    }

    /**
     * Manage avatar. Owner-only — separate ability so an admin role can be
     * granted later via `Gate::before` without touching the rest.
     */
    public function manageAvatar(User $user, User $account): bool
    {
        return $user->id === $account->id;
    }
}
