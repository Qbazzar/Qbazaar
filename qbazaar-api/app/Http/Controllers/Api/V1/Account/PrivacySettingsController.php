<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Actions\Account\UpdatePrivacySettingsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Account\UpdatePrivacySettingsRequest;
use App\Http\Resources\Api\V1\Account\PrivacySettingsResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Account
 */
class PrivacySettingsController extends Controller
{
    /**
     * Return the user's current privacy settings.
     *
     * @authenticated
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->authorize('view', $user);

        return response()->json(
            (new PrivacySettingsResource($user->privacySettings()))->toArray($request),
        );
    }

    /**
     * Replace the user's privacy settings.
     *
     * @authenticated
     */
    public function update(UpdatePrivacySettingsRequest $request, UpdatePrivacySettingsAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->authorize('update', $user);

        /** @var array{show_phone: bool, show_email: bool, allow_chat: bool, indexed_by_search: bool} $payload */
        $payload = $request->validated();

        $settings = $action->execute($user, $payload);

        return response()->json(
            (new PrivacySettingsResource($settings))->toArray($request),
        );
    }
}
