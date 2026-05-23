<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Actions\Account\UpdateProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Account\UpdateProfileRequest;
use App\Http\Resources\Api\V1\Account\AccountProfileResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Account
 */
class ProfileController extends Controller
{
    /**
     * Retrieve the signed-in user's profile + privacy settings.
     *
     * @authenticated
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->authorize('view', $user);

        return response()->json(
            (new AccountProfileResource($user))->toArray($request),
        );
    }

    /**
     * Update the editable subset of the profile (full_name + language).
     *
     * @authenticated
     */
    public function update(UpdateProfileRequest $request, UpdateProfileAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->authorize('update', $user);

        /** @var array{full_name: string, language: string} $payload */
        $payload = $request->validated();

        $updated = $action->execute($user, $payload);

        return response()->json(
            (new AccountProfileResource($updated))->toArray($request),
        );
    }
}
