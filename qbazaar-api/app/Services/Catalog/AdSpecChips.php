<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Enums\CategoryFieldType;
use App\Models\Ad;
use App\Rules\NoMarkup;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Support\Str;

/**
 * The "spec chips" of a listing card: the ad's condition, then the values of
 * the category fields flagged `show_in_card`, in the category's order and in
 * the requested language. Which fields count is decided by that flag alone,
 * set per category in its custom-field definitions (inherited ones
 * included, {@see CategoryFields}). Reads only the ad, its loaded category
 * and the cached schema, so a page of cards costs no query.
 */
#[Scoped]
class AdSpecChips
{
    private const CONDITION_KEY = 'condition';

    public function __construct(
        private readonly CategoryFieldPresenter $fields,
        private readonly CategoryFields $schema,
    ) {}

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

        foreach ($this->schema->forCategory($ad->category)->definitions() as $field) {
            $chip = $this->fieldChip($field, $values, $locale);

            if ($chip !== null) {
                $chips[$chip['key']] ??= $chip;
            }
        }

        return array_slice(array_values($chips), 0, (int) config('qbazaar.cards.max_spec_chips'));
    }

    /**
     * Values saved before custom fields refused markup are left off the card.
     *
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

        $display = $this->displayValue($field, $values[$key] ?? null, $locale);

        if ($display === null) {
            return null;
        }

        return [
            'key' => $key,
            'label' => $this->fields->label($field, $locale),
            'value' => Str::limit($display, (int) config('qbazaar.cards.spec_chip_max_length'), '…'),
        ];
    }

    /**
     * A multiselect value is a list; its chip joins the chosen options.
     *
     * @param array<string, mixed> $field
     */
    private function displayValue(array $field, mixed $value, string $locale): ?string
    {
        if (is_array($value) && $field['type'] !== CategoryFieldType::MULTISELECT->value) {
            return null;
        }

        $parts = is_array($value) ? array_values($value) : [$value];
        $shown = [];

        foreach ($parts as $part) {
            if (! is_scalar($part) || $part === '' || NoMarkup::isFoundIn($part)) {
                return null;
            }

            $shown[] = $this->display($field, $part, $locale);
        }

        return $shown === [] ? null : implode($locale === 'ar' ? '، ' : ', ', $shown);
    }

    /**
     * @param array<string, mixed> $field
     */
    private function display(array $field, string|int|float|bool $value, string $locale): string
    {
        return match ($field['type'] ?? null) {
            'select', 'multiselect' => $this->fields->optionLabel($field, $value, $locale),
            'boolean' => (string) trans(filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'catalog.yes' : 'catalog.no', [], $locale),
            default => (string) $value,
        };
    }
}
