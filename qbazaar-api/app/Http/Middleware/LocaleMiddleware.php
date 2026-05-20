<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the request locale from (in order):
 *   1. ?lang=ar|en query param (explicit override, useful for testing)
 *   2. Authenticated user's `language` column
 *   3. Accept-Language header (first match against supported list)
 *   4. config('qbazaar.default_language')
 *
 * The resolved locale is set on the App facade so __() / trans() / Carbon
 * helpers see the right value for the rest of the request lifecycle.
 */
class LocaleMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('qbazaar.supported_languages');
        $default = config('qbazaar.default_language');

        $locale = $this->resolve($request, $supported, $default);

        App::setLocale($locale);

        return $next($request);
    }

    /** @param  array<int,string>  $supported */
    private function resolve(Request $request, array $supported, string $default): string
    {
        // 1. Query override
        if (in_array($request->query('lang'), $supported, true)) {
            return $request->query('lang');
        }

        // 2. Authenticated user preference
        $user = $request->user();
        if ($user && isset($user->language) && in_array($user->language, $supported, true)) {
            return $user->language;
        }

        // 3. Accept-Language header — take the first supported tag
        $header = $request->header('Accept-Language');
        if (is_string($header)) {
            foreach (explode(',', $header) as $tag) {
                $primary = strtolower(trim(explode(';', $tag)[0]));
                $short = substr($primary, 0, 2);
                if (in_array($short, $supported, true)) {
                    return $short;
                }
            }
        }

        return $default;
    }
}
