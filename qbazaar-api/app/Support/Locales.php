<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\App;

/**
 * Helpers for payloads that are cached once per supported language.
 */
final class Locales
{
    /**
     * @return list<string>
     */
    public static function supported(): array
    {
        /** @var list<string> $locales */
        $locales = config('qbazaar.supported_languages');

        return $locales;
    }

    /**
     * @template TValue
     *
     * @param Closure(): TValue $callback
     * @return TValue
     */
    public static function within(string $locale, Closure $callback): mixed
    {
        $previous = App::getLocale();
        App::setLocale($locale);

        try {
            return $callback();
        } finally {
            App::setLocale($previous);
        }
    }
}
