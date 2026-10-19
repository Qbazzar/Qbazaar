<?php

declare(strict_types=1);

namespace App\Services\Ads;

use App\Enums\CategoryFieldType;
use App\Models\Category;
use App\Rules\NoMarkup;
use App\Services\Catalog\CategoryFields;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Dynamic validator for category-specific `custom_fields` payloads.
 *
 * The schema is the category's effective one: its own definitions plus the
 * ones inherited from its ancestors ({@see CategoryFields}). Each field type
 * becomes a ruleset, and failures carry `custom_fields.{key}` paths so the
 * API envelope surfaces them under the existing VALIDATION_FAILED code.
 *
 *   - text        → string, max 255, no markup
 *   - number      → numeric within ±qbazaar.ads.custom_field_number_max
 *   - range       → a number like `number`, inside the field's min/max
 *   - boolean     → boolean
 *   - select      → one of the options; without options it is validated as text
 *   - multiselect → a list of distinct options
 *
 * Keys outside the schema are kept as sent.
 */
class CustomFieldsValidator
{
    public function __construct(private readonly CategoryFields $fields) {}

    /**
     * @param array<string, mixed>|null $submitted
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(Category $category, ?array $submitted): array
    {
        $schema = $this->fields->forCategory($category)->definitions();
        $submitted ??= [];

        if ($schema === []) {
            return $submitted;
        }

        $rules = [];

        foreach ($schema as $field) {
            $rules += $this->rulesFor($field);
        }

        $validator = ValidatorFactory::make(['custom_fields' => $submitted], $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $submitted;
    }

    /**
     * @param array<string, mixed> $field canonical definition
     * @return array<string, list<mixed>>
     */
    private function rulesFor(array $field): array
    {
        $path = 'custom_fields.' . $field['key'];
        $presence = $field['required'] === true ? ['required'] : ['nullable', 'sometimes'];
        /** @var list<string>|null $options */
        $options = $field['options'];

        return match (CategoryFieldType::fromStored($field['type'])) {
            CategoryFieldType::NUMBER, CategoryFieldType::RANGE => [$path => [...$presence, 'numeric', ...$this->numberBounds($field)]],
            CategoryFieldType::BOOLEAN => [$path => [...$presence, 'boolean']],
            CategoryFieldType::SELECT => [$path => [...$presence, ...($options !== null ? [Rule::in($options)] : $this->textRules())]],
            CategoryFieldType::MULTISELECT => [
                $path => [...$presence, 'array', 'max:' . count($options ?? [])],
                $path . '.*' => ['string', 'distinct', Rule::in($options ?? [])],
            ],
            CategoryFieldType::TEXT => [$path => [...$presence, ...$this->textRules()]],
        };
    }

    /**
     * Field values are shown on listing cards, so a number may not be an
     * endless digit string; a field's own bounds narrow that further.
     *
     * @param array<string, mixed> $field
     * @return list<string>
     */
    private function numberBounds(array $field): array
    {
        $limit = (int) config('qbazaar.ads.custom_field_number_max');
        $bounds = ["between:-{$limit},{$limit}"];

        if (is_numeric($field['min'])) {
            $bounds[] = 'min:' . $field['min'];
        }

        if (is_numeric($field['max'])) {
            $bounds[] = 'max:' . $field['max'];
        }

        return $bounds;
    }

    /**
     * @return list<mixed>
     */
    private function textRules(): array
    {
        return ['string', 'max:255', new NoMarkup];
    }
}
