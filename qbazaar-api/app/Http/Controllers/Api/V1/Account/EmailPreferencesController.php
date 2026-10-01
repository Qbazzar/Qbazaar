<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Actions\Account\UpdateNotificationPreferencesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Account\UpdateEmailPreferencesRequest;
use App\Http\Resources\Api\V1\Account\EmailPreferencesResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The email half of the notification preferences, for the settings screen
 * that only manages email.
 *
 * @group Account
 */
class EmailPreferencesController extends Controller
{
    /**
     * Email switch per notification topic.
     *
     * @authenticated
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(
            (new EmailPreferencesResource($user->notification_preferences))->resolve($request),
        );
    }

    /**
     * Change any subset of the email switches.
     *
     * @authenticated
     */
    public function update(UpdateEmailPreferencesRequest $request, UpdateNotificationPreferencesAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $preferences = $action->execute($user, $request->switches());

        return response()->json((new EmailPreferencesResource($preferences))->resolve($request));
    }
}
