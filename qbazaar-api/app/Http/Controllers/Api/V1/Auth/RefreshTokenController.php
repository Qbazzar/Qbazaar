<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\RefreshRequest;
use App\Http\Resources\Api\V1\AuthResponseResource;
use App\Services\Auth\DeviceFingerprintService;
use App\Services\Auth\RefreshTokenService;
use Illuminate\Http\JsonResponse;

/**
 * @group Auth
 */
class RefreshTokenController extends Controller
{
    /**
     * Rotate refresh token
     *
     * Rotates the presented refresh token. The old one is marked used (replay
     * detection kicks in if anyone tries to reuse it later) and a brand-new
     * access + refresh pair is minted and returned.
     *
     * Failures (expired / invalid / replay) raise a DomainException carrying
     * AUTH_TOKEN_EXPIRED or AUTH_TOKEN_INVALID — see RefreshTokenService.
     *
     * @unauthenticated
     *
     * @response 200 scenario="Success" {
     *   "success": true,
     *   "data": {
     *     "user": { "id": "01HF5KX9Y6XR7Z9R3E0HK2X6FC" },
     *     "tokens": {
     *       "access_token": "2|abcd...",
     *       "refresh_token": "rt_01hf5kx9y6xr7z9r3e0hk2x6fc...",
     *       "token_type": "Bearer",
     *       "expires_in": 900
     *     }
     *   }
     * }
     *
     * @response 401 scenario="Invalid or expired token" {
     *   "success": false,
     *   "error": {
     *     "code": "AUTH_010",
     *     "message_key": "errors.auth.token.invalid",
     *     "message": "The token is invalid.",
     *     "details": null
     *   }
     * }
     */
    public function __invoke(RefreshRequest $request, RefreshTokenService $service, DeviceFingerprintService $fingerprints): JsonResponse
    {
        $result = $service->rotate(
            presentedRaw: (string) $request->validated('refresh_token'),
            deviceFingerprint: $fingerprints->fingerprintFromRequest($request),
            ip: (string) $request->ip(),
            deviceLabel: $fingerprints->labelFromRequest($request),
        );

        return response()->json(
            (new AuthResponseResource($result['user'], $result['tokens']))->toArray(),
        );
    }
}
