<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Authorization rules for blocking / unblocking another user.
 *
 * Important design note: the deeper business rules ("admins cannot be
 * blocked", "self-block forbidden") live in BlockUserAction so they can
 * throw the stable `USER_002` / `USER_003` ErrorCodes via DomainException
 * (HTTP 403 / 422 with predictable `error.code` for the JSON envelope).
 *
 * We deliberately keep this policy permissive — it only asserts the caller
 * is authenticated (handled by the route middleware) — so the action can
 * surface its precise codes. If the policy was strict and refused
 * self-block here, callers would receive a generic 403 FORBIDDEN, which
 * breaks the existing contract documented in `error-codes.md`.
 *
 * The policy still exists so future admin-side bypass logic (e.g.
 * `Gate::before` returning true for admins) lands in one place.
 */
class BlockPolicy
{
    public function block(User $blocker, User $target): bool
    {
        unset($blocker, $target);

        return true;
    }

    public function unblock(User $blocker, User $target): bool
    {
        unset($blocker, $target);

        return true;
    }
}
