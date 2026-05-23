<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Actions\Account\UpdatePasswordAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Account\UpdatePasswordRequest;
use App\Models\User;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @group Account
 */
class PasswordController extends Controller
{
    /**
     * Change the signed-in user's password and burn other sessions.
     *
     * On success returns 204 — the current access token stays valid so the
     * caller doesn't need to re-authenticate on the active device. Every
     * OTHER active Sanctum token and refresh token is invalidated.
     *
     * @authenticated
     *
     * @response 204 scenario="Success" {}
     */
    public function __invoke(UpdatePasswordRequest $request, UpdatePasswordAction $action): Response
    {
        /** @var User $user */
        $user = $request->user();

        $this->authorize('update', $user);

        /** @var PersonalAccessToken|null $current */
        $current = $user->currentAccessToken() instanceof PersonalAccessToken
            ? $user->currentAccessToken()
            : null;

        $action->execute(
            $user,
            (string) $request->validated('current_password'),
            (string) $request->validated('password'),
            $current,
        );

        return response()->noContent();
    }
}
