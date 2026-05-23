<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Account\VerificationStatusResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Account
 */
class VerificationStatusController extends Controller
{
    /**
     * Get the current user's verification status across email, phone, and
     * (placeholder) business + KYC flags.
     *
     * @authenticated
     */
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->authorize('view', $user);

        return response()->json(
            (new VerificationStatusResource($user))->toArray($request),
        );
    }
}
