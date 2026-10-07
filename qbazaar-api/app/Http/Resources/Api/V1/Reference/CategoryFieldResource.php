<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Reference;

use App\Services\Catalog\CategoryFieldPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Custom-field definition used when posting an ad inside a given category
 * (e.g. Cars → make, model, year). Source is the JSON entry stored on
 * `categories.custom_fields`; select labels follow {@see CategoryFieldPresenter}.
 *
 * @property array{
 *     key?: string,
 *     label?: array{ar?: string, en?: string},
 *     type?: string,
 *     required?: bool,
 *     options?: array<int, mixed>|null,
 *     option_labels?: array<string, array{ar?: string, en?: string}>,
 *     show_in_card?: bool,
 * } $resource
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
            'options' => $data['options'] ?? null,
            'options_labeled' => $presented['options_labeled'],
            'show_in_card' => $presented['show_in_card'],
        ];
    }
}
