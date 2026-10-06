<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Ad;

/**
 * The "spec chips" of a listing card: the ad's condition, then the values of
 * the category fields flagged `show_in_card`, in the category's order and in
 * the requested language. Which fields count is decided by that flag alone,
 * set per category in its custom-field definitions. Reads only the ad and
 * its loaded category.
 */
class AdSpecChips
{
    private const CONDITION_KEY = 'condition';

    public function __construct(private readonly CategoryFieldPresenter $fields) {}

    /**
     * @return list<array{key: string, label: string, value: string}>
     */
    public function for(Ad $ad, string $locale): array
    {
        $chips = [];

        if ($ad->condition !== null) {
            $chips[self::CONDITION_KEY] = [
                'key' => self::CONDITION_KEY,
                'label' => (string) trans('catalog.condition', [], $locale),
                'value' => $ad->condition->label()[$locale] ?? $ad->condition->label()['en'],
            ];
        }

        $values = $ad->custom_fields ?? [];

        foreach ($ad->category->custom_fields ?? [] as $field) {
            $chip = is_array($field) ? $this->fieldChip($field, $values, $locale) : null;

            if ($chip !== null) {
                $chips[$chip['key']] ??= $chip;
            }
        }

        return array_slice(array_values($chips), 0, (int) config('qbazaar.cards.max_spec_chips'));
    }

    /**
     * @param array<string, mixed> $field
     * @param array<string, mixed> $values
     * @return array{key: string, label: string, value: string}|null
     */
    private function fieldChip(array $field, array $values, string $locale): ?array
    {
        $key = $field['key'] ?? null;

        if (! is_string($key) || ! $this->fields->showsInCard($field)) {
            return null;
        }

        $value = $values[$key] ?? null;

        if (! is_scalar($value) || $value === '') {
            return null;
        }

        return [
            'key' => $key,
            'label' => $this->fields->label($field, $locale),
            'value' => $this->display($field, $value, $locale),
        ];
    }

    /**
     * @param array<string, mixed> $field
     */
    private function display(array $field, string|int|float|bool $value, string $locale): string
    {
        return match ($field['type'] ?? null) {
            'select' => $this->fields->optionLabel($field, $value, $locale),
            'boolean' => (string) trans(filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'catalog.yes' : 'catalog.no', [], $locale),
            default => (string) $value,
        };
    }
}
