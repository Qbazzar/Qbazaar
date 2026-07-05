<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->toString();

        $categories = Category::query()
            ->with('parent')
            ->when(
                $search !== '',
                fn ($query) => $query->where(function ($inner) use ($search): void {
                    $inner->where('slug', 'like', "%{$search}%")
                        ->orWhere('name->ar', 'like', "%{$search}%")
                        ->orWhere('name->en', 'like', "%{$search}%");
                }),
            )
            ->orderBy('order')
            ->paginate(30)
            ->withQueryString();

        return view('manage.categories.index', [
            'categories' => $categories,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('manage.categories.form', [
            'category' => new Category,
            'parents' => $this->parentOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        Category::create($data);
        $this->flushCache();

        return redirect()
            ->route('manage.categories.index')
            ->with('status', 'تم إنشاء التصنيف بنجاح.');
    }

    public function edit(Category $category): View
    {
        return view('manage.categories.form', [
            'category' => $category,
            'parents' => $this->parentOptions($category->id),
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $this->validated($request, $category);

        $category->update($data);
        $this->flushCache();

        return redirect()
            ->route('manage.categories.index')
            ->with('status', 'تم تحديث التصنيف بنجاح.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();
        $this->flushCache();

        return redirect()
            ->route('manage.categories.index')
            ->with('status', 'تم حذف التصنيف.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Category $category = null): array
    {
        $validated = $request->validate([
            'name.ar' => ['required', 'string', 'max:255'],
            'name.en' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:64',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('categories', 'slug')->ignore($category?->id),
            ],
            'parent_id' => [
                'nullable',
                'string',
                Rule::exists('categories', 'id'),
                Rule::notIn(array_filter([$category?->id])),
            ],
            'icon' => ['nullable', 'string', 'max:64'],
            'order' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'custom_fields' => ['nullable', 'string', $this->jsonRule()],
            'custom_filters' => ['nullable', 'string', $this->jsonRule()],
        ]);

        return [
            'name' => ['ar' => $validated['name']['ar'], 'en' => $validated['name']['en']],
            'slug' => $validated['slug'],
            'parent_id' => $validated['parent_id'] ?? null,
            'icon' => $validated['icon'] ?? null,
            'order' => $validated['order'],
            'is_active' => $request->boolean('is_active'),
            'custom_fields' => $this->decodeJson($validated['custom_fields'] ?? null),
            'custom_filters' => $this->decodeJson($validated['custom_filters'] ?? null),
        ];
    }

    /**
     * Validation closure ensuring a string field holds valid JSON.
     */
    private function jsonRule(): callable
    {
        return static function (string $attribute, mixed $value, callable $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            json_decode((string) $value, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $fail('يجب أن يكون الحقل :attribute بصيغة JSON صحيحة.');
            }
        };
    }

    /**
     * @return array<mixed>|null
     */
    private function decodeJson(?string $value): ?array
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        /** @var array<mixed>|null $decoded */
        $decoded = json_decode($value, true);

        return $decoded;
    }

    /**
     * Build the parent-select options, excluding the record being edited so a
     * category can't become its own parent.
     *
     * @return Collection<int, Category>
     */
    private function parentOptions(?string $excludeId = null)
    {
        return Category::query()
            ->when($excludeId !== null, fn ($query) => $query->whereKeyNot($excludeId))
            ->orderBy('order')
            ->get();
    }

    /**
     * Bust the public taxonomy caches — mirrors CategoryResource::flushCache().
     */
    private function flushCache(): void
    {
        Cache::forget('categories.tree');
        Cache::forget('categories.main');

        try {
            Cache::tags(['categories'])->flush();
        } catch (Throwable) {
            // Driver doesn't support tags (file / array) — keys expire by TTL.
        }
    }
}
