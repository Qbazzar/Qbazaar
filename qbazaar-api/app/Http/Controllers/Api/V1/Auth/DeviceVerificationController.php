<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\VerifyNewDeviceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\DeviceVerifyRequest;
use App\Http\Resources\Api\V1\SignInResponse;
use Illuminate\Http\JsonResponse;

/**
 * @group Auth
 */
class DeviceVerificationController extends Controller
{
    /**
     * Verify a new device with the SMS code
     *
     * Completes a sign-in that answered 202. Errors: 401 AUTH_012 unknown or
     * expired challenge, 410 AUTH_004 / 422 AUTH_005 for the code.
     *
     * @unauthenticated
     */
    public function __invoke(DeviceVerifyRequest $request, VerifyNewDeviceAction $action): JsonResponse
    {
        $result = $action->execute(
            challengeToken: (string) $request->validated('challenge_token'),
            code: (string) $request->validated('code'),
        );

        return (new SignInResponse($result))->toResponse();
    }
}
