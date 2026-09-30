<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\RefreshTokenService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @group Auth
 */
class LogoutController extends Controller
{
    /**
     * Log out the current session
     *
     * Revokes the current bearer token together with the refresh token issued
     * alongside it. A `refresh_token` body field, if sent, is revoked as well.
     * Returns 204.
     *
     * @authenticated
     *
     * @bodyParam refresh_token string optional Refresh token to revoke alongside the access token. Example: rt_01hf5kx9y6xr7z9r3e0hk2x6fc...
     *
     * @response 204 scenario="Success" {}
     */
    public function __invoke(Request $request, RefreshTokenService $refreshTokens): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $refreshTokens->revokeSession($token);
        }

        $presentedRefresh = $request->input('refresh_token');
        if (is_string($presentedRefresh) && $presentedRefresh !== '') {
            $refreshTokens->revoke($presentedRefresh);
        }

        return response()->noContent();
    }
}
