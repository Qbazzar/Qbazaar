<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tags every API request with the calling client platform.
 *
 * Resolution order:
 *   1. X-Client-Platform header (when the client cooperates) — values: web, mobile_ios, mobile_android, admin
 *   2. User-Agent sniffing      — Flutter / browser substrings
 *
 * The result lives on the request attribute bag as `client_platform`. Anyone
 * downstream can read it via $request->attributes->get('client_platform').
 *
 * Used by: analytics, deprecation warnings, feature flags, audit log.
 */
class TrackClient
{
    private const ALLOWED_HEADER_VALUES = ['web', 'mobile_ios', 'mobile_android', 'admin'];

    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('X-Client-Platform');

        if (is_string($header) && in_array(strtolower($header), self::ALLOWED_HEADER_VALUES, true)) {
            $platform = strtolower($header);
        } else {
            $platform = $this->sniffFromUserAgent($request->userAgent() ?? '');
        }

        $request->attributes->set('client_platform', $platform);

        return $next($request);
    }

    private function sniffFromUserAgent(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'Flutter') => 'mobile_unknown',
            str_contains($ua, 'Dart') => 'mobile_unknown',
            str_contains($ua, 'Mozilla') => 'web',
            default => 'unknown',
        };
    }
}
