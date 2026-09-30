<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\User;
use App\Services\Admin\AdminAuditLogger;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\NewAccessToken;

/**
 * Mints a short-lived access token for browsing the marketplace as a user.
 * No refresh token is issued, so the borrowed session cannot outlive the TTL.
 */
class ImpersonateUserAction
{
    public const TOKEN_NAME = 'impersonation';

    public function __construct(private readonly AdminAuditLogger $audit) {}

    public function execute(User $admin, User $user, string $reason, ?string $ip): NewAccessToken
    {
        $expiresAt = Carbon::now()->addMinutes((int) config('qbazaar.admin.impersonation_ttl_minutes'));

        $token = $user->createToken(self::TOKEN_NAME, ['*'], $expiresAt);
        $token->accessToken->forceFill([
            'ip_address' => $ip,
            'device_label' => self::TOKEN_NAME,
        ])->save();

        $this->audit->record($admin, 'admin.users.impersonated', $user, [
            'reason' => $reason,
            'token_id' => $token->accessToken->getKey(),
            'expires_at' => $expiresAt->toIso8601String(),
            'ip' => $ip,
        ]);

        return $token;
    }
}
