<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage\Catalog;

use App\Services\Catalog\Taxonomy\TaxonomyKind;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Activates or deactivates a category or a location: the way out when a
 * row in use cannot be deleted.
 */
class UpdateTaxonomyStatusRequest extends FormRequest
{
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
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function kind(): TaxonomyKind
    {
        return TaxonomyRoute::kind($this);
    }

    public function active(): bool
    {
        return $this->boolean('is_active');
    }
}
