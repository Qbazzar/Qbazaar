<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\SocialSignInAction;
use App\Enums\SocialProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\SocialSignInRequest;
use App\Http\Resources\Api\V1\SignInResponse;
use App\Services\Auth\DeviceFingerprintService;
use Illuminate\Http\JsonResponse;

/**
 * @group Auth
 */
class SocialSignInController extends Controller
{
    /**
     * Sign in with Google or Apple
     *
     * 200 signed in, 201 account created, 202 new-device SMS challenge,
     * 401 AUTH_013 bad token, 422 AUTH_011 sign-up details needed,
     * 503 AUTH_015 provider not configured.
     *
     * @unauthenticated
     */
    public function __invoke(SocialSignInRequest $request, SocialProvider $provider, SocialSignInAction $action, DeviceFingerprintService $devices): JsonResponse
    {
        $result = $action->execute(
            provider: $provider,
            idToken: (string) $request->validated('id_token'),
            registration: $request->registrationDetails(),
            device: $devices->contextFromRequest($request),
        );

        return (new SignInResponse($result))->toResponse();
    }
}
