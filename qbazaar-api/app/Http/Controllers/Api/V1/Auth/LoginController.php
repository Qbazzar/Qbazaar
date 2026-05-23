<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\LoginUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\Api\V1\AuthResponseResource;
use Illuminate\Http\JsonResponse;

class LoginController extends Controller
{
    /**
     * POST /api/v1/auth/login
     *
     * Accepts an `identifier` (email OR +974… phone) plus password.
     * Returns 200 + AuthResponseEnvelope on success, or:
     *  - 401 AUTH_001 on bad credentials
     *  - 403 AUTH_002 if the account is suspended
     */
    public function __invoke(LoginRequest $request, LoginUserAction $action): JsonResponse
    {
        $platform = $request->attributes->get('client_platform');
        $fingerprint = is_string($platform) ? $platform : null;

        $result = $action->execute(
            identifier: (string) $request->validated('identifier'),
            password: (string) $request->validated('password'),
            deviceFingerprint: $fingerprint,
        );

        return response()->json(
            (new AuthResponseResource($result['user'], $result['tokens']))->toArray(),
        );
    }
}
