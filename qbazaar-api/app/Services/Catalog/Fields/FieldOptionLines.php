<?php

declare(strict_types=1);

namespace App\Services\Catalog\Fields;

use App\Services\Catalog\CategoryFieldPresenter;

/**
 * The options of a select field as the admin types them: one per line,
 * `value | Arabic label | English label`, where both labels are optional.
 * The value is what ads store and filters match; the labels are what people
 * read ({@see CategoryFieldPresenter}).
 */
final class FieldOptionLines
{
    private const SEPARATOR = '|';

    /**
     * @return list<array{value: string, ar: string, en: string}>
     */
    public static function parse(string $text): array
    {
        $rows = [];

        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }

            $parts = array_map('trim', explode(self::SEPARATOR, $line, 3));

            $rows[] = ['value' => $parts[0], 'ar' => $parts[1] ?? '', 'en' => $parts[2] ?? ''];
        }

        return $rows;
    }

    /**
     * @param list<array{value: string, ar: string, en: string}> $rows
     * @return array{options: list<string>, option_labels: array<string, array{ar: string, en: string}>}
     */
    public static function toDefinition(array $rows): array
    {
        $options = [];
        $labels = [];

        foreach ($rows as $row) {
            $options[] = $row['value'];

            if ($row['ar'] !== '' || $row['en'] !== '') {
                $labels[$row['value']] = ['ar' => $row['ar'], 'en' => $row['en']];
            }
        }

        return ['options' => $options, 'option_labels' => $labels];
    }

    /**
     * The textarea text for a canonical definition, so editing round-trips.
     *
     * @param array<string, mixed> $definition
     */
    public static function format(array $definition): string
    {
        /** @var list<string> $options */
        $options = is_array($definition['options'] ?? null) ? $definition['options'] : [];
        /** @var array<string, array{ar?: string, en?: string}> $labels */
        $labels = is_array($definition['option_labels'] ?? null) ? $definition['option_labels'] : [];

        return implode("\n", array_map(static function (string $option) use ($labels): string {
            $label = $labels[$option] ?? [];
            $ar = (string) ($label['ar'] ?? '');
            $en = (string) ($label['en'] ?? '');

            if ($ar === '' && $en === '') {
                return $option;
            }

            return implode(' ' . self::SEPARATOR . ' ', [$option, $ar, $en]);
        }, $options));
    }
}
