<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage\Catalog;

use App\Models\Category;
use App\Rules\NoMarkup;
use App\Rules\NotInOwnSubtree;
use App\Services\Catalog\Taxonomy\TaxonomyKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creates or edits a category's own attributes. Its custom fields are kept
 * as they are: they change only through the fields editor.
 */
class SaveCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(TaxonomyKind::CATEGORY->permission()) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $id = $this->category()?->id;

        return [
            'name.ar' => ['required', 'string', 'max:255', new NoMarkup],
            'name.en' => ['required', 'string', 'max:255', new NoMarkup],
            'slug' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('categories', 'slug')->ignore($id)],
            'parent_id' => [
                'nullable',
                'string',
                Rule::exists('categories', 'id'),
                Rule::notIn(array_filter([$id])),
                new NotInOwnSubtree(TaxonomyKind::CATEGORY, $id),
            ],
            'icon' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
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
            'parent_id' => (string) __('admin.catalog.attributes.parent_category'),
            'icon' => (string) __('admin.catalog.attributes.icon'),
            'order' => (string) __('admin.catalog.attributes.order'),
        ];
    }

    /**
     * @return array{name: array{ar: string, en: string}, slug: string, parent_id: string|null, icon: string|null, order: int, is_active: bool}
     */
    public function categoryData(): array
    {
        return [
            'name' => ['ar' => $this->string('name.ar')->toString(), 'en' => $this->string('name.en')->toString()],
            'slug' => $this->string('slug')->toString(),
            'parent_id' => $this->filled('parent_id') ? $this->string('parent_id')->toString() : null,
            'icon' => $this->filled('icon') ? $this->string('icon')->toString() : null,
            'order' => $this->integer('order'),
            'is_active' => $this->boolean('is_active'),
        ];
    }

    private function category(): ?Category
    {
        $category = $this->route('category');

        return $category instanceof Category ? $category : null;
    }
}
