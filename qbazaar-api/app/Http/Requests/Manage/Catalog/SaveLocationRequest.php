<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage\Catalog;

use App\Enums\LocationType;
use App\Models\Location;
use App\Rules\NoMarkup;
use App\Rules\NotInOwnSubtree;
use App\Services\Catalog\Taxonomy\TaxonomyKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(TaxonomyKind::LOCATION->permission()) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $id = $this->location()?->id;

        return [
            'name.ar' => ['required', 'string', 'max:255', new NoMarkup],
            'name.en' => ['required', 'string', 'max:255', new NoMarkup],
            'slug' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('locations', 'slug')->ignore($id)],
            'type' => ['required', Rule::enum(LocationType::class)],
            'parent_id' => [
                'nullable',
                'string',
                Rule::exists('locations', 'id'),
                Rule::notIn(array_filter([$id])),
                new NotInOwnSubtree(TaxonomyKind::LOCATION, $id),
            ],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'order' => ['required', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name.ar' => (string) __('admin.catalog.attributes.name_ar'),
            'name.en' => (string) __('admin.catalog.attributes.name_en'),
            'slug' => (string) __('admin.catalog.attributes.slug'),
            'type' => (string) __('admin.catalog.attributes.type'),
            'parent_id' => (string) __('admin.catalog.attributes.parent_location'),
            'lat' => (string) __('admin.catalog.attributes.lat'),
            'lng' => (string) __('admin.catalog.attributes.lng'),
            'order' => (string) __('admin.catalog.attributes.order'),
        ];
    }

    /**
     * @return array{name: array{ar: string, en: string}, slug: string, type: string, parent_id: string|null, lat: string|null, lng: string|null, order: int, is_active: bool}
     */
    public function locationData(): array
    {
        return [
            'name' => ['ar' => $this->string('name.ar')->toString(), 'en' => $this->string('name.en')->toString()],
            'slug' => $this->string('slug')->toString(),
            'type' => $this->string('type')->toString(),
            'parent_id' => $this->optionalString('parent_id'),
            'lat' => $this->optionalString('lat'),
            'lng' => $this->optionalString('lng'),
            'order' => $this->integer('order'),
            'is_active' => $this->boolean('is_active'),
        ];
    }

    private function optionalString(string $key): ?string
    {
        return $this->filled($key) ? $this->string($key)->toString() : null;
    }

    private function location(): ?Location
    {
        $location = $this->route('location');

        return $location instanceof Location ? $location : null;
    }
}
