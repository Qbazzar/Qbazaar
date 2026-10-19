<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage\Catalog;

use App\Enums\CategoryFieldType;
use App\Models\Category;
use App\Rules\NoMarkup;
use App\Services\Catalog\Fields\CategoryFieldEditor;
use App\Services\Catalog\Fields\FieldDefinitions;
use App\Services\Catalog\Fields\FieldOptionLines;
use App\Services\Catalog\Taxonomy\TaxonomyKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * One custom field of a category, from the fields editor. The key is chosen
 * when the field is added and never changes afterwards, because ads store
 * their values under it.
 */
class SaveCategoryFieldRequest extends FormRequest
{
    public const LABEL_MAX = 80;

    public const MAX_OPTIONS = 200;

    public const SORT_ORDER_MAX = 100_000;

    /** What ads store and search filters match: a letter or digit first, no markup or separators. */
    public const OPTION_VALUE_PATTERN = '/^[\p{L}\p{N}][\p{L}\p{N} _.+\-]{0,63}$/u';

    public function authorize(): bool
    {
        return $this->user()?->can(TaxonomyKind::CATEGORY->permission()) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $limit = (int) config('qbazaar.ads.custom_field_number_max');
        $bound = ['nullable', 'numeric', "between:-{$limit},{$limit}", Rule::requiredIf(fn (): bool => $this->type() === CategoryFieldType::RANGE)];

        return [
            ...($this->isUpdate() ? [] : ['key' => ['required', 'string', 'regex:' . FieldDefinitions::KEY_PATTERN]]),
            'type' => ['required', Rule::enum(CategoryFieldType::class)],
            'label.ar' => ['required', 'string', 'max:' . self::LABEL_MAX, new NoMarkup],
            'label.en' => ['required', 'string', 'max:' . self::LABEL_MAX, new NoMarkup],
            'required' => ['sometimes', 'boolean'],
            'filterable' => ['sometimes', 'boolean'],
            'show_in_card' => ['sometimes', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:' . self::SORT_ORDER_MAX],
            'options' => ['nullable', 'string', 'max:20000', Rule::requiredIf(fn (): bool => $this->type()?->hasOptions() === true)],
            'min' => $bound,
            'max' => $bound,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'key' => (string) __('admin.catalog.fields.attributes.key'),
            'type' => (string) __('admin.catalog.fields.attributes.type'),
            'label.ar' => (string) __('admin.catalog.fields.attributes.label_ar'),
            'label.en' => (string) __('admin.catalog.fields.attributes.label_en'),
            'sort_order' => (string) __('admin.catalog.fields.attributes.sort_order'),
            'options' => (string) __('admin.catalog.fields.attributes.options'),
            'min' => (string) __('admin.catalog.fields.attributes.min'),
            'max' => (string) __('admin.catalog.fields.attributes.max'),
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->checkKeyIsFree($validator);
                $this->checkBounds($validator);
                $this->checkOptions($validator);
            },
        ];
    }

    /**
     * The definition to store, in the canonical shape of {@see FieldDefinitions}.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->type() ?? CategoryFieldType::TEXT;

        $definition = [
            'key' => $this->fieldKey(),
            'type' => $type->value,
            'label' => ['ar' => $this->string('label.ar')->trim()->toString(), 'en' => $this->string('label.en')->trim()->toString()],
            'required' => $this->boolean('required'),
            'filterable' => $this->boolean('filterable'),
            'show_in_card' => $this->boolean('show_in_card'),
            'sort_order' => $this->integer('sort_order'),
            'options' => null,
            'option_labels' => [],
            'min' => null,
            'max' => null,
        ];

        if ($type->hasOptions()) {
            $definition = [...$definition, ...FieldOptionLines::toDefinition($this->optionRows())];
        }

        if ($type->isNumeric()) {
            $definition['min'] = $this->number('min');
            $definition['max'] = $this->number('max');
        }

        return $definition;
    }

    public function fieldKey(): string
    {
        $routeKey = $this->route('field');

        return is_string($routeKey) ? $routeKey : $this->string('key')->toString();
    }

    /** Whole numbers stay integers, so a bound reads "1990" rather than "1990.0". */
    private function number(string $key): int|float|null
    {
        $value = $this->input($key);

        return is_numeric($value) ? $value + 0 : null;
    }

    private function isUpdate(): bool
    {
        return is_string($this->route('field'));
    }

    private function type(): ?CategoryFieldType
    {
        $type = $this->input('type');

        return is_string($type) ? CategoryFieldType::tryFrom($type) : null;
    }

    /**
     * A new field may take the key of an inherited field (that overrides it)
     * or of one this category hid, but not of a field the category declares.
     */
    private function checkKeyIsFree(Validator $validator): void
    {
        $category = $this->route('category');

        if ($this->isUpdate() || ! $category instanceof Category) {
            return;
        }

        $own = app(CategoryFieldEditor::class)->ownEntry($category, $this->fieldKey());

        if ($own !== null && ! FieldDefinitions::isHidden($own)) {
            $validator->errors()->add('key', (string) __('admin.catalog.fields.validation.key_taken'));
        }
    }

    private function checkBounds(Validator $validator): void
    {
        if ($this->type()?->isNumeric() === true && $this->filled(['min', 'max']) && $this->float('max') < $this->float('min')) {
            $validator->errors()->add('max', (string) __('admin.catalog.fields.validation.max_below_min'));
        }
    }

    private function checkOptions(Validator $validator): void
    {
        if ($this->type()?->hasOptions() !== true) {
            return;
        }

        $error = $this->optionsError($this->optionRows());

        if ($error !== null) {
            $validator->errors()->add('options', $error);
        }
    }

    /**
     * @param list<array{value: string, ar: string, en: string}> $rows
     */
    private function optionsError(array $rows): ?string
    {
        if ($rows === []) {
            return (string) __('admin.catalog.fields.validation.options_required');
        }

        if (count($rows) > self::MAX_OPTIONS) {
            return (string) __('admin.catalog.fields.validation.options_too_many', ['max' => self::MAX_OPTIONS]);
        }

        $seen = [];

        foreach ($rows as $index => $row) {
            $line = $index + 1;

            if (preg_match(self::OPTION_VALUE_PATTERN, $row['value']) !== 1) {
                return (string) __('admin.catalog.fields.validation.option_value', ['line' => $line]);
            }

            if (isset($seen[mb_strtolower($row['value'])])) {
                return (string) __('admin.catalog.fields.validation.option_duplicate', ['line' => $line, 'value' => $row['value']]);
            }

            if (mb_strlen($row['ar']) > self::LABEL_MAX || mb_strlen($row['en']) > self::LABEL_MAX || NoMarkup::isFoundIn($row['ar'] . $row['en'])) {
                return (string) __('admin.catalog.fields.validation.option_label', ['line' => $line, 'max' => self::LABEL_MAX]);
            }

            $seen[mb_strtolower($row['value'])] = true;
        }

        return null;
    }

    /**
     * @return list<array{value: string, ar: string, en: string}>
     */
    private function optionRows(): array
    {
        return FieldOptionLines::parse($this->string('options')->toString());
    }
}
