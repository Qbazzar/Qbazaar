<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Reference;

use App\Services\Catalog\CategoryFieldPresenter;
use App\Services\Catalog\CategoryFields;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Custom-field definition used when posting an ad inside a given category
 * (e.g. Cars → make, model, year), inherited fields included. Source is a
 * canonical definition from {@see CategoryFields}; select labels follow
 * {@see CategoryFieldPresenter}.
 *
 * @property array<string, mixed> $resource
 */
class CategoryFieldResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = is_array($this->resource) ? $this->resource : [];

        $presented = app(CategoryFieldPresenter::class)->field($data, app()->getLocale());

        return [
            'key' => (string) ($data['key'] ?? ''),
            'label' => $data['label'] ?? ['ar' => '', 'en' => ''],
            'type' => (string) ($data['type'] ?? 'text'),
            'required' => (bool) ($data['required'] ?? false),
            'filterable' => (bool) ($data['filterable'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'options' => $data['options'] ?? null,
            'options_labeled' => $presented['options_labeled'],
            'min' => $data['min'] ?? null,
            'max' => $data['max'] ?? null,
            'show_in_card' => $presented['show_in_card'],
        ];
    }
}
