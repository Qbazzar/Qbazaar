<?php

declare(strict_types=1);

namespace App\Services\Admin\Catalog;

use App\Enums\CategoryFieldType;
use App\Services\Catalog\Fields\FieldDefinitions;
use App\Services\Catalog\Fields\FieldOptionLines;

/**
 * The values the field form opens with: empty for a new field, an
 * inherited definition to override, or the category's own one to edit.
 */
final readonly class CategoryFieldForm
{
    public const CREATE = 'create';

    public const OVERRIDE = 'override';

    public const EDIT = 'edit';

    public function __construct(
        public string $mode,
        public string $key,
        public string $type,
        public string $labelAr,
        public string $labelEn,
        public bool $required,
        public bool $filterable,
        public bool $showInCard,
        public int $sortOrder,
        public string $options,
        public string $min,
        public string $max,
        public ?string $inheritedFrom = null,
    ) {}

    /**
     * @param array<string, mixed> $definition canonical, see {@see FieldDefinitions}
     */
    public static function fromDefinition(string $mode, array $definition, ?string $inheritedFrom = null): self
    {
        /** @var array{ar: string, en: string} $label */
        $label = $definition['label'];

        return new self(
            mode: $mode,
            key: (string) $definition['key'],
            type: (string) $definition['type'],
            labelAr: $label['ar'],
            labelEn: $label['en'],
            required: $definition['required'] === true,
            filterable: $definition['filterable'] === true,
            showInCard: $definition['show_in_card'] === true,
            sortOrder: (int) $definition['sort_order'],
            options: FieldOptionLines::format($definition),
            min: is_numeric($definition['min']) ? (string) $definition['min'] : '',
            max: is_numeric($definition['max']) ? (string) $definition['max'] : '',
            inheritedFrom: $inheritedFrom,
        );
    }

    public static function blank(int $sortOrder): self
    {
        return new self(self::CREATE, '', CategoryFieldType::TEXT->value, '', '', false, false, false, $sortOrder, '', '', '');
    }

    public function isEdit(): bool
    {
        return $this->mode === self::EDIT;
    }
}
