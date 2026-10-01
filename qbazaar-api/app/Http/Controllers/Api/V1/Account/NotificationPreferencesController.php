<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Actions\Account\UpdateNotificationPreferencesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Account\UpdateNotificationPreferencesRequest;
use App\Http\Resources\Api\V1\Account\NotificationPreferencesResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Account
 */
class NotificationPreferencesController extends Controller
{
    /**
     * Email and push switches for every notification topic.
     *
     * @authenticated
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(
            (new NotificationPreferencesResource($user->notification_preferences))->resolve($request),
        );
    }

    /**
     * Replace every email and push switch.
     *
     * @authenticated
     */
    public function update(UpdateNotificationPreferencesRequest $request, UpdateNotificationPreferencesAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $preferences = $action->execute($user, $request->switches('email'), $request->switches('push'));

        return response()->json((new NotificationPreferencesResource($preferences))->resolve($request));
    }
}
