<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks any route that requires a verified phone (OTP completed).
 *
 * Contract:
 *  - MUST be applied AFTER `auth:sanctum`. Without an authenticated user we
 *    respond 401 AUTH_TOKEN_INVALID — same shape as Sanctum's own failure.
 *  - When `$user->phone_verified` is false we respond 403 AUTH_003.
 *
 * Why not combine with EnsureUserIsActive?
 *  - Some endpoints need only the active check (e.g. settings), and we don't
 *    want to drag the phone-verified gate into them. Splitting keeps the
 *    middleware list opt-in per route.
 */
class EnsurePhoneVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user instanceof User) {
            throw new DomainException(ErrorCode::AUTH_TOKEN_INVALID);
        }

        if (! $user->phone_verified) {
            throw new DomainException(ErrorCode::AUTH_PHONE_NOT_VERIFIED);
        }

        return $next($request);
    }
}
