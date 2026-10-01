<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Illuminate\Http\Request;

/**
 * Identifies the device behind a request.
 *
 * Clients send a stable random id in the X-Device-Id header (generated once
 * and kept in secure storage). Only its hash is stored. Older clients that do
 * not send it fall back to a server-side fingerprint (platform + truncated UA
 * + IP); that one changes with the network, so those clients see the
 * new-device check more often.
 */
class DeviceFingerprintService
{
    public const HEADER = 'X-Device-Id';

    private const DEVICE_ID_PATTERN = '/^[A-Za-z0-9._:-]{16,128}$/';

    public function contextFromRequest(Request $request): DeviceContext
    {
        return new DeviceContext(
            hash: $this->fingerprintFromRequest($request),
            label: $this->labelFromRequest($request),
            ip: (string) $request->ip(),
        );
    }

    public function fingerprintFromRequest(Request $request): string
    {
        $deviceId = (string) $request->header(self::HEADER);

        if (preg_match(self::DEVICE_ID_PATTERN, $deviceId) === 1) {
            return hash('sha256', 'device-id|' . $deviceId);
        }

        $platform = $request->attributes->get('client_platform');
        $platform = is_string($platform) ? $platform : 'unknown';

        // Full Chrome UAs run 200+ chars with build numbers that defeat
        // stability; the first 80 chars capture engine + major version.
        $ua = substr((string) $request->userAgent(), 0, 80);

        return hash('sha256', $platform . '|' . $ua . '|' . $request->ip());
    }

    /**
     * Human-readable label for the device, derived from platform + UA.
     * Used by the sessions list and SecurityAlertNotification.
     */
    public function labelFromRequest(Request $request): string
    {
        $platform = $request->attributes->get('client_platform');
        $platform = is_string($platform) ? $platform : 'unknown';

        $ua = (string) $request->userAgent();
        $short = $this->shortBrowser($ua);

        return $short === '' ? $platform : ($platform . ' / ' . $short);
    }

    private function shortBrowser(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Firefox/') => 'Firefox',
            // Chrome arm matched first; if we reach here Chrome/ isn't in the UA, so Safari/ alone is enough.
            str_contains($ua, 'Safari/') => 'Safari',
            str_contains($ua, 'Flutter') => 'Flutter',
            str_contains($ua, 'Dart') => 'Dart',
            default => '',
        };
    }
}
