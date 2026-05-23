<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Users;

use App\Actions\Users\BlockUserAction;
use App\Actions\Users\UnblockUserAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * @group Users
 */
class BlockController extends Controller
{
    /**
     * Block another user.
     *
     * Errors:
     *  - 422 USER_003 — cannot block yourself
     *  - 403 USER_002 — cannot block an admin
     *  - 404 USER_001 — target user does not exist (handled by route model binding)
     *
     * Idempotent: duplicate blocks return 200 + the same payload.
     *
     * @authenticated
     */
    public function store(Request $request, BlockUserAction $action, User $user): JsonResponse
    {
        /** @var User $blocker */
        $blocker = $request->user();

        // BlockPolicy::block catches self-block and admin-target before we
        // touch the action; the action still throws stable ErrorCodes on top
        // of that so HTTP clients keep getting USER_002 / USER_003.
        $this->authorize('block-user', $user);

        $action->execute($blocker, $user);

        return response()->json([
            'blocked' => true,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Unblock another user. Always 204 even when no block existed.
     *
     * @authenticated
     *
     * @response 204 scenario="Unblocked" {}
     */
    public function destroy(Request $request, UnblockUserAction $action, User $user): Response
    {
        /** @var User $blocker */
        $blocker = $request->user();

        $this->authorize('unblock-user', $user);

        $action->execute($blocker, $user);

        return response()->noContent();
    }
}
