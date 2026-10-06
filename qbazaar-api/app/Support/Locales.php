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

    /**
     * Runs the callback once in every supported language, so one database
     * pass can feed the cached payload of each.
     *
     * @template TValue
     *
     * @param Closure(): TValue $callback
     * @return array<string, TValue> keyed by language
     */
    public static function each(Closure $callback): array
    {
        $results = [];

        foreach (self::supported() as $locale) {
            $results[$locale] = self::within($locale, $callback);
        }

        return $results;
    }

    /**
     * The entry for the current language, or for the default language when
     * the app runs in one that is not supported (a console command).
     *
     * @template TValue
     *
     * @param array<string, TValue> $byLocale
     * @return TValue
     */
    public static function pick(array $byLocale): mixed
    {
        return $byLocale[App::getLocale()] ?? $byLocale[(string) config('qbazaar.default_language')];
    }
}
