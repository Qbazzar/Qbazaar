<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Account\SessionResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @group Account
 */
class SessionsController extends Controller
{
    /**
     * List the user's active Sanctum sessions.
     *
     * "Active" = expires_at is in the future (or null). Expired tokens are
     * automatically excluded so the client only sees what it can still use.
     * The session row matching the currently-authenticated token is flagged
     * with `is_current=true` so the UI can show a "this device" badge.
     *
     * @authenticated
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->authorize('view', $user);

        /** @var PersonalAccessToken|null $current */
        $current = $user->currentAccessToken() instanceof PersonalAccessToken
            ? $user->currentAccessToken()
            : null;

        $currentId = $current?->getKey();

        /** @var Collection<int, PersonalAccessToken> $tokens */
        $tokens = $user->tokens()
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->get();

        $data = $tokens->map(function (PersonalAccessToken $token) use ($currentId, $request): array {
            $token->setAttribute('is_current', $token->getKey() === $currentId);

            return (new SessionResource($token))->toArray($request);
        })->all();

        return response()->json($data);
    }

    /**
     * Revoke a single Sanctum session by id.
     *
     * Trying to revoke a session that doesn't belong to the caller returns
     * USER_001 (NotFound) to avoid leaking other users' token ids.
     *
     * @authenticated
     *
     * @response 204 scenario="Revoked" {}
     *
     * @throws DomainException
     */
    public function destroy(Request $request, string $id): Response
    {
        /** @var User $user */
        $user = $request->user();

        $this->authorize('update', $user);

        /** @var PersonalAccessToken|null $token */
        $token = $user->tokens()->whereKey($id)->first();

        if ($token === null) {
            throw new DomainException(ErrorCode::USER_NOT_FOUND);
        }

        $token->delete();

        return response()->noContent();
    }
}
