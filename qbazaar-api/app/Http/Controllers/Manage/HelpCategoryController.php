<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\HelpCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HelpCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->toString();

        $categories = HelpCategory::query()
            ->withCount('articles')
            ->when(
                $search !== '',
                fn ($query) => $query->where(function ($inner) use ($search): void {
                    $inner->where('name->ar', 'like', "%{$search}%")
                        ->orWhere('name->en', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                }),
            )
            ->orderBy('display_order')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.help-categories.index', [
            'categories' => $categories,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('admin.help-categories.form', ['category' => new HelpCategory]);
    }

    public function store(Request $request): RedirectResponse
    {
        HelpCategory::create($this->validated($request));
        $this->flushCache();

        return redirect()
            ->route('admin.help-categories.index')
            ->with('status', 'تم إنشاء القسم بنجاح.');
    }

    public function edit(HelpCategory $help_category): View
    {
        return view('admin.help-categories.form', ['category' => $help_category]);
    }

    public function update(Request $request, HelpCategory $help_category): RedirectResponse
    {
        $help_category->update($this->validated($request, $help_category));
        $this->flushCache();

        return redirect()
            ->route('admin.help-categories.index')
            ->with('status', 'تم تحديث القسم بنجاح.');
    }

    public function destroy(HelpCategory $help_category): RedirectResponse
    {
        $help_category->delete();
        $this->flushCache();

        return redirect()
            ->route('admin.help-categories.index')
            ->with('status', 'تم حذف القسم.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['string'],
        ]);

        $count = HelpCategory::whereIn('id', $data['ids'])->delete();
        $this->flushCache();

        return back()->with('status', "تم حذف {$count} عنصراً.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?HelpCategory $category = null): array
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:64', 'alpha_dash', Rule::unique('help_categories', 'slug')->ignore($category?->id)],
            'icon' => ['nullable', 'string', 'max:64'],
            'display_order' => ['required', 'integer', 'min:0'],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'description_ar' => ['nullable', 'string', 'max:1000'],
            'description_en' => ['nullable', 'string', 'max:1000'],
        ]);

        return [
            'slug' => $validated['slug'],
            'icon' => $validated['icon'] ?? null,
            'display_order' => $validated['display_order'],
            'name' => ['ar' => $validated['name_ar'], 'en' => $validated['name_en'] ?? ''],
            'description' => [
                'ar' => $validated['description_ar'] ?? '',
                'en' => $validated['description_en'] ?? '',
            ],
        ];
    }

    private function flushCache(): void
    {
        Cache::forget('help.categories');
    }
}
