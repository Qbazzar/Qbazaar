<?php

declare(strict_types=1);

namespace App\Services\Catalog\Fields;

use App\Enums\CategoryFieldType;

/**
 * Reads the custom-field definitions stored on `categories.custom_fields`
 * into one canonical shape. Rows saved by the fields editor are already
 * canonical; older rows were typed as JSON by hand, so every key is read
 * leniently and anything without a usable key is dropped.
 *
 * A definition is either a field:
 *   {key, type, label{ar,en}, required, filterable, show_in_card,
 *    sort_order, options, option_labels, min, max}
 * or a marker that hides a field inherited from an ancestor:
 *   {key, hidden: true}
 */
final class FieldDefinitions
{
    public const KEY_PATTERN = '/^[a-z][a-z0-9_]{0,39}$/';

    /** Older rows used mixed-case keys such as `storage_GB`; they stay readable. */
    private const STORED_KEY_PATTERN = '/^[A-Za-z0-9_]{1,64}$/';

    private const SORT_STEP = 10;

    /**
     * @param array<mixed>|null $stored
     * @return list<array<string, mixed>>
     */
    public static function normalizeList(?array $stored): array
    {
        $definitions = [];

        foreach (array_values($stored ?? []) as $position => $raw) {
            $definition = self::normalize($raw, $position);

            if ($definition !== null) {
                $definitions[] = $definition;
            }
        }

        return $definitions;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function normalize(mixed $raw, int $position = 0): ?array
    {
        if (! is_array($raw) || ! is_string($raw['key'] ?? null) || preg_match(self::STORED_KEY_PATTERN, $raw['key']) !== 1) {
            return null;
        }

        if (self::flag($raw['hidden'] ?? false)) {
            return ['key' => $raw['key'], 'hidden' => true];
        }

        $type = CategoryFieldType::fromStored($raw['type'] ?? null);

        return [
            'key' => $raw['key'],
            'type' => $type->value,
            'label' => self::translations($raw['label'] ?? null),
            'required' => self::flag($raw['required'] ?? false),
            'filterable' => self::flag($raw['filterable'] ?? false),
            'show_in_card' => self::flag($raw['show_in_card'] ?? false),
            'sort_order' => is_numeric($raw['sort_order'] ?? null) ? (int) $raw['sort_order'] : ($position + 1) * self::SORT_STEP,
            'options' => $type->hasOptions() ? self::options($raw['options'] ?? null) : null,
            'option_labels' => $type->hasOptions() ? self::optionLabels($raw['option_labels'] ?? null) : [],
            'min' => $type->isNumeric() ? self::number($raw['min'] ?? null) : null,
            'max' => $type->isNumeric() ? self::number($raw['max'] ?? null) : null,
        ];
    }

    /**
     * @param array<string, mixed> $definition
     */
    public static function isHidden(array $definition): bool
    {
        return ($definition['hidden'] ?? false) === true;
    }

    /**
     * Admins type these by hand in older rows: "false" and "0" are false.
     */
    private static function flag(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array{ar: string, en: string}
     */
    private static function translations(mixed $value): array
    {
        $value = is_array($value) ? $value : [];

        return [
            'ar' => is_string($value['ar'] ?? null) ? $value['ar'] : '',
            'en' => is_string($value['en'] ?? null) ? $value['en'] : '',
        ];
    }

    /**
     * @return list<string>|null
     */
    private static function options(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $options = array_values(array_unique(array_map(
            static fn (mixed $option): string => (string) $option,
            array_filter($value, static fn (mixed $option): bool => is_scalar($option) && (string) $option !== ''),
        )));

        return $options === [] ? null : $options;
    }

    /**
     * @return array<string, array{ar: string, en: string}>
     */
    private static function optionLabels(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $labels = [];

        foreach ($value as $option => $translations) {
            if (is_array($translations)) {
                $labels[(string) $option] = self::translations($translations);
            }
        }

        return $labels;
    }

    private static function number(mixed $value): int|float|null
    {
        return is_numeric($value) ? $value + 0 : null;
    }
}
