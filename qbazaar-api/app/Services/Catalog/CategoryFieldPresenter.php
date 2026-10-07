<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use Illuminate\Container\Attributes\Scoped;
use Illuminate\Support\Str;

/**
 * Reads the custom-field definitions stored on a category (admin JSON) and
 * shapes them for clients in the request language.
 *
 * Select options keep their raw values in `options`, which ads store and
 * search filters match, and gain `options_labeled`: each value with its
 * label. A definition may carry `option_labels` as `{value: {ar, en}}`; an
 * option without one falls back to English, then to the value itself made
 * readable. `option_labels` stays internal.
 */
#[Scoped]
class CategoryFieldPresenter
{
    /**
     * @param array<int, mixed>|null $fields
     * @return array<int, mixed>|null
     */
    public function present(?array $fields, string $locale): ?array
    {
        if ($fields === null) {
            return null;
        }

        return array_map(
            fn (mixed $field): mixed => is_array($field) ? $this->field($field, $locale) : $field,
            $fields,
        );
    }

    /**
     * @param array<string, mixed> $field
     * @return array<string, mixed>
     */
    public function field(array $field, string $locale): array
    {
        $options = $field['options'] ?? null;

        $presented = array_diff_key($field, ['option_labels' => true]);
        $presented['options_labeled'] = is_array($options)
            ? array_map(
                fn (mixed $value): array => ['value' => $value, 'label' => $this->optionLabel($field, $value, $locale)],
                array_values($options),
            )
            : null;
        $presented['show_in_card'] = $this->showsInCard($field);

        return $presented;
    }

    /**
     * Whether the field's value is one of the spec chips on listing cards.
     * Read leniently because admins type this JSON by hand: "false" is false.
     *
     * @param array<string, mixed> $field
     */
    public function showsInCard(array $field): bool
    {
        return filter_var($field['show_in_card'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param array<string, mixed> $field
     */
    public function label(array $field, string $locale): string
    {
        $key = $field['key'] ?? '';

        return $this->translated($field['label'] ?? null, $locale) ?? (is_string($key) ? $key : '');
    }

    /**
     * The display text of one stored option value.
     *
     * @param array<string, mixed> $field The stored definition, `option_labels` included.
     */
    public function optionLabel(array $field, mixed $value, string $locale): string
    {
        $raw = is_scalar($value) ? (string) $value : '';

        return $this->translated($field['option_labels'][$raw] ?? null, $locale) ?? $this->readable($raw);
    }

    private function translated(mixed $translations, string $locale): ?string
    {
        if (! is_array($translations)) {
            return null;
        }

        foreach ([$locale, 'en'] as $language) {
            $text = $translations[$language] ?? null;

            if (is_string($text) && $text !== '') {
                return $text;
            }
        }

        return null;
    }

    /**
     * Machine values such as `semi_furnished` read as "Semi furnished"; names
     * such as "BMW" or "OnePlus" stay as they are.
     */
    private function readable(string $value): string
    {
        return Str::ucfirst(str_replace('_', ' ', $value));
    }
}
