<?php

declare(strict_types=1);

namespace App\Services\Admin\Catalog;

use App\Enums\CategoryFieldType;

/**
 * One field of the fields editor: a field the category declares, one that
 * replaces an inherited definition, or one it inherits as is.
 */
final readonly class CategoryFieldRow
{
    public const OWN = 'own';

    public const OVERRIDE = 'override';

    public const INHERITED = 'inherited';

    public function __construct(
        public string $key,
        public string $labelAr,
        public string $labelEn,
        public CategoryFieldType $type,
        public bool $required,
        public bool $filterable,
        public bool $showInCard,
        public int $sortOrder,
        public int $optionCount,
        public string $source,
        public ?string $sourceName,
    ) {}

    public function isOwn(): bool
    {
        return $this->source !== self::INHERITED;
    }

    public function label(string $locale): string
    {
        $label = $locale === 'ar' ? $this->labelAr : $this->labelEn;

        return $label !== '' ? $label : ($this->labelEn !== '' ? $this->labelEn : $this->key);
    }
}
