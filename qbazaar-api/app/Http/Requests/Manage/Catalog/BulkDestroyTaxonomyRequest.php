<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage\Catalog;

use App\Services\Catalog\Taxonomy\TaxonomyKind;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A selection of categories or locations to delete, from the bulk bar of
 * their list. A selection never spans more than a page, so it is capped.
 */
class BulkDestroyTaxonomyRequest extends FormRequest
{
    public const MAX_IDS = 100;

    public function authorize(): bool
    {
        return $this->user()?->can($this->kind()->permission()) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:' . self::MAX_IDS],
            'ids.*' => ['required', 'string', 'ulid', 'distinct'],
        ];
    }

    public function kind(): TaxonomyKind
    {
        return TaxonomyRoute::kind($this);
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        /** @var list<string> $ids */
        $ids = array_values($this->array('ids'));

        return $ids;
    }
}
