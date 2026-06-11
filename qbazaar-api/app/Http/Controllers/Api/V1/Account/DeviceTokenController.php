<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Account\RegisterDeviceTokenRequest;
use App\Http\Resources\Api\V1\Account\DeviceTokenResource;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Register / unregister FCM device tokens for web push.
 *
 * Ownership is keyed on the TOKEN, not the (user, token) pair: an FCM token
 * identifies a physical browser/device, and the device belongs to whoever
 * logged in last. Re-registering an existing token therefore re-points the
 * row at the caller instead of duplicating it.
 *
 * @group Account
 */
class DeviceTokenController extends Controller
{
    /**
     * POST /api/v1/account/device-tokens — register (or refresh) a token.
     *
     * Idempotent upsert: 201 on first registration, 200 when the token was
     * already known (same or different user). `last_used_at` is re-stamped
     * on every call so a scheduled prune can drop tokens that stopped
     * refreshing.
     *
     * @authenticated
     */
    public function store(RegisterDeviceTokenRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array{token: string, platform?: string} $validated */
        $validated = $request->validated();

        $deviceToken = DeviceToken::query()->updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $user->id,
                'platform' => $validated['platform'] ?? 'web',
                'last_used_at' => now(),
            ],
        );

        return response()
            ->json((new DeviceTokenResource($deviceToken))->toArray($request))
            ->setStatusCode(
                $deviceToken->wasRecentlyCreated
                    ? SymfonyResponse::HTTP_CREATED
                    : SymfonyResponse::HTTP_OK,
            );
    }

    /**
     * DELETE /api/v1/account/device-tokens — unregister a token.
     *
     * Always 204, even for unknown tokens or tokens owned by someone else
     * (silent no-op) — a non-204 would let a caller probe which tokens
     * exist in the system.
     *
     * @authenticated
     *
     * @response 204 scenario="Unregistered" {}
     */
    public function destroy(Request $request): Response
    {
        /** @var array{token: string} $validated */
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $user->deviceTokens()
            ->where('token', $validated['token'])
            ->delete();

        return response()->noContent();
    }
}
