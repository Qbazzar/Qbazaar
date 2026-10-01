<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Uploads;

use App\Actions\Account\RemoveAvatarAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * @group Uploads
 */
class RemoveAvatarController extends Controller
{
    /**
     * Remove the signed-in user's avatar. Idempotent: 204 even when there is
     * no avatar.
     *
     * @authenticated
     */
    public function __invoke(Request $request, RemoveAvatarAction $action): Response
    {
        /** @var User $user */
        $user = $request->user();

        $this->authorize('manageAvatar', $user);

        $action->execute($user);

        return response()->noContent();
    }
}
