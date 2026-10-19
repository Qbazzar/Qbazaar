<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Reference;

use App\Services\Catalog\CategoryFieldPresenter;
use App\Services\Catalog\CategoryFields;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Filter definition exposed to the frontend so it can render category-specific
 * search controls: one per field marked filterable, inherited fields included
 * ({@see CategoryFields::filters()}). `type` is the control: select, range,
 * boolean or text.
 *
 * @property array<string, mixed> $resource
 */
class CategoryFilterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = is_array($this->resource) ? $this->resource : [];

        $presented = app(CategoryFieldPresenter::class)->filter($data, app()->getLocale());

        return [
            'key' => (string) ($data['key'] ?? ''),
            'label' => $data['label'] ?? ['ar' => '', 'en' => ''],
            'type' => (string) ($data['type'] ?? 'select'),
            'options' => $data['options'] ?? null,
            'options_labeled' => $presented['options_labeled'],
            'min' => $data['min'] ?? null,
            'max' => $data['max'] ?? null,
        ];
    }
}
