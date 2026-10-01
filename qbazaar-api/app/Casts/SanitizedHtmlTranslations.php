<?php

declare(strict_types=1);

namespace App\Casts;

use App\Services\Content\HtmlSanitizer;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A per-locale HTML body (`{"ar": "...", "en": "..."}`) that is sanitized on
 * every write, so no code path can store markup the web would execute.
 *
 * @implements CastsAttributes<array<string, string>, array<string, string|null>>
 */
class SanitizedHtmlTranslations implements CastsAttributes
{
    /**
     * @param array<string, mixed> $attributes
     * @return array<string, string>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $sanitizer = app(HtmlSanitizer::class);
        $clean = array_map(
            fn (?string $html): string => $sanitizer->sanitize((string) $html),
            (array) $value,
        );

        return json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
