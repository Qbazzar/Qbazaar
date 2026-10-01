<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Limiters for the unauthenticated auth endpoints. Mobile carriers put many
 * users behind one address (CGNAT), so each limit is keyed by what the
 * request is about (an account, a refresh token, an inbox) and the address
 * alone only gets a much higher ceiling.
 */
final class AuthRateLimiters
{
    public static function register(): void
    {
        RateLimiter::for('auth', fn (Request $request): array => [
            Limit::perMinute(self::limit('attempts_per_minute'))->by('auth:' . $request->ip() . '|' . self::subject($request)),
            Limit::perMinute(self::limit('attempts_per_minute_per_ip'))->by('auth-ip:' . $request->ip()),
        ]);

        RateLimiter::for('refresh', fn (Request $request): array => [
            Limit::perMinute(self::limit('refresh_per_minute_per_token'))->by('refresh:' . self::refreshTokenId($request)),
            Limit::perMinute(self::limit('refresh_per_minute_per_ip'))->by('refresh-ip:' . $request->ip()),
        ]);

        // Each hit mails a link, so the inbox is capped whoever asks for it.
        RateLimiter::for('email-links', fn (Request $request): Limit => Limit::perHour(self::limit('email_links_per_hour'))
            ->by('email-links:' . (self::inputString($request, 'email') ?: mb_strtolower((string) $request->user()?->email))));
    }

    private static function limit(string $key): int
    {
        return (int) config("qbazaar.auth.rate_limits.{$key}");
    }

    /**
     * The account an attempt targets. Social sign-in carries no account
     * name, so its provider token stands in and only the IP ceiling binds.
     */
    private static function subject(Request $request): string
    {
        foreach (['identifier', 'email', 'phone'] as $field) {
            $value = self::inputString($request, $field);

            if ($value !== '') {
                return $value;
            }
        }

        $idToken = $request->input('id_token');

        return is_string($idToken) ? sha1($idToken) : '';
    }

    /**
     * The row id embedded after the `rt_` prefix, so retries of one token
     * share a bucket however the rest of the string is mangled.
     */
    private static function refreshTokenId(Request $request): string
    {
        return substr(self::inputString($request, 'refresh_token'), 0, 29);
    }

    /**
     * Limiters run before validation, so a non-string value must still yield
     * a key instead of an array-to-string error.
     */
    private static function inputString(Request $request, string $field): string
    {
        $value = $request->input($field);

        return is_string($value) ? mb_strtolower(trim($value)) : '';
    }
}
