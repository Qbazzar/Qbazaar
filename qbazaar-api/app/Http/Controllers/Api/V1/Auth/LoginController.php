<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\LoginUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\Api\V1\SignInResponse;
use App\Services\Auth\DeviceFingerprintService;
use Illuminate\Http\JsonResponse;

/**
 * @group Auth
 */
class LoginController extends Controller
{
    /**
     * Log in with email or phone
     *
     * Accepts an `identifier` (email OR +974… phone) plus password.
     * Returns 200 + AuthResponseEnvelope on success, 202 + a device challenge
     * when the device is not trusted yet (finish at /auth/device/verify), or:
     *  - 401 AUTH_001 on bad credentials
     *  - 403 AUTH_002 if the account is suspended
     *  - 403 AUTH_014 when password sign-in is turned off
     *
     * @unauthenticated
     *
     * @response 200 scenario="Success" {
     *   "success": true,
     *   "data": {
     *     "user": {
     *       "id": "01HF5KX9Y6XR7Z9R3E0HK2X6FC",
     *       "full_name": "Ahmed Al-Ali",
     *       "email": "ahmed@example.qa",
     *       "phone": "+97455123456"
     *     },
     *     "tokens": {
     *       "access_token": "1|abcd...",
     *       "refresh_token": "rt_01hf5kx9y6xr7z9r3e0hk2x6fc...",
     *       "token_type": "Bearer",
     *       "expires_in": 900
     *     }
     *   }
     * }
     *
     * @response 401 scenario="Invalid credentials" {
     *   "success": false,
     *   "error": {
     *     "code": "AUTH_001",
     *     "message_key": "errors.auth.invalid.credentials",
     *     "message": "Invalid credentials.",
     *     "details": null
     *   }
     * }
     */
    public function __invoke(LoginRequest $request, LoginUserAction $action, DeviceFingerprintService $devices): JsonResponse
    {
        $result = $action->execute(
            identifier: (string) $request->validated('identifier'),
            password: (string) $request->validated('password'),
            device: $devices->contextFromRequest($request),
        );

        return (new SignInResponse($result))->toResponse();
    }
}
