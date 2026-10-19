<?php

declare(strict_types=1);

namespace App\Services\Admin\TwoFactor;

use App\Models\StaffTwoFactor;
use App\Models\User;
use Illuminate\Cookie\CookieJar;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * "Trust this browser": a cookie (encrypted and signed by the web stack's
 * EncryptCookies middleware) naming the staff member, a fingerprint of their
 * credential and an expiry. A matching cookie skips the code prompt after
 * the password. The fingerprint covers the secret and the device version, so
 * re-enrolling or bumping the version revokes every trusted browser at once.
 */
class TrustedBrowser
{
    public const string COOKIE = 'qb_admin_2fa_trust';

    public function __construct(private readonly CookieJar $cookies) {}

    public function remember(User $user, StaffTwoFactor $credential): void
    {
        $days = (int) config('qbazaar.admin.two_factor.trusted_browser_days');

        $this->cookies->queue($this->cookies->make(
            self::COOKIE,
            $this->payloadFor($user, $credential, Carbon::now()->addDays($days)),
            $days * 24 * 60,
            '/admin',
            null,
            config('session.secure'),
            true,
            false,
            'lax',
        ));
    }

    public function recognises(Request $request, User $user, StaffTwoFactor $credential): bool
    {
        $raw = $request->cookie(self::COOKIE);
        if (! is_string($raw) || $raw === '') {
            return false;
        }

        $payload = json_decode($raw, true);

        return is_array($payload)
            && ($payload['u'] ?? null) === $user->id
            && is_string($payload['f'] ?? null)
            && hash_equals($this->fingerprint($credential), $payload['f'])
            && is_int($payload['e'] ?? null)
            && $payload['e'] > Carbon::now()->getTimestamp();
    }

    /** The cookie's plain value, before the web stack encrypts it. */
    public function payloadFor(User $user, StaffTwoFactor $credential, Carbon $expiresAt): string
    {
        return json_encode([
            'u' => $user->id,
            'f' => $this->fingerprint($credential),
            'e' => $expiresAt->getTimestamp(),
        ], JSON_THROW_ON_ERROR);
    }

    private function fingerprint(StaffTwoFactor $credential): string
    {
        return hash_hmac('sha256', $credential->device_version . '|' . $credential->secret, (string) config('app.key'));
    }
}
