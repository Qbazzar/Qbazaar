<?php

declare(strict_types=1);

namespace App\Services\Admin\Inbox;

/**
 * Turns the link stored in a notification into a relative path inside the
 * panel, or null. Notification payloads are data, so only same-host /admin
 * pages are followed: anything else would make "open" an open redirect.
 */
final class AdminTarget
{
    private const string PREFIX = '/admin';

    public static function from(?string $url): ?string
    {
        $parts = $url === null || $url === '' ? false : parse_url($url);

        if ($parts === false || ! self::sameHost($parts)) {
            return null;
        }

        $path = (string) ($parts['path'] ?? '');

        if (! self::isPanelPath($path)) {
            return null;
        }

        return $path
            . (isset($parts['query']) ? '?' . $parts['query'] : '')
            . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
    }

    /**
     * @param array<string, int|string> $parts
     */
    private static function sameHost(array $parts): bool
    {
        if (isset($parts['scheme']) && ! in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        if (! isset($parts['host'])) {
            return ! isset($parts['scheme']);
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($appHost) && strcasecmp($appHost, (string) $parts['host']) === 0;
    }

    private static function isPanelPath(string $path): bool
    {
        if ($path !== self::PREFIX && ! str_starts_with($path, self::PREFIX . '/')) {
            return false;
        }

        return ! str_contains($path, '..') && ! str_contains($path, '//') && ! str_contains($path, '\\');
    }
}
