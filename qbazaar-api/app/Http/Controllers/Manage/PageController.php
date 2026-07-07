<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PageController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->toString();
        $published = $request->string('published')->toString();

        $pages = Page::query()
            ->when(
                $search !== '',
                fn ($query) => $query->where(function ($inner) use ($search): void {
                    $inner->where('title->ar', 'like', "%{$search}%")
                        ->orWhere('title->en', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                }),
            )
            ->when(
                in_array($published, ['1', '0'], true),
                fn ($query) => $query->where('is_published', $published === '1'),
            )
            ->orderBy('display_order')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.pages.index', [
            'pages' => $pages,
            'search' => $search,
            'published' => $published,
        ]);
    }

    public function create(): View
    {
        return view('admin.pages.form', ['page' => new Page]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        Page::create($data);
        $this->flushCache();

        return redirect()
            ->route('admin.pages.index')
            ->with('status', 'تم إنشاء الصفحة بنجاح.');
    }

    public function edit(Page $page): View
    {
        return view('admin.pages.form', ['page' => $page]);
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $data = $this->validated($request, $page);

        $page->update($data);
        $this->flushCache($page->slug);

        return redirect()
            ->route('admin.pages.index')
            ->with('status', 'تم تحديث الصفحة بنجاح.');
    }

    public function destroy(Page $page): RedirectResponse
    {
        $slug = $page->slug;
        $page->delete();
        $this->flushCache($slug);

        return redirect()
            ->route('admin.pages.index')
            ->with('status', 'تم حذف الصفحة.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['string'],
        ]);

        $count = Page::whereIn('id', $data['ids'])->delete();
        $this->flushCache();

        return back()->with('status', "تم حذف {$count} عنصراً.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Page $page = null): array
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:64', 'alpha_dash', Rule::unique('pages', 'slug')->ignore($page?->id)],
            'display_order' => ['required', 'integer', 'min:0'],
            'is_published' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'title_ar' => ['required', 'string', 'max:255'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'body_ar' => ['required', 'string'],
            'body_en' => ['nullable', 'string'],
            'meta_description_ar' => ['nullable', 'string', 'max:500'],
            'meta_description_en' => ['nullable', 'string', 'max:500'],
        ]);

        return [
            'slug' => $validated['slug'],
            'display_order' => $validated['display_order'],
            'is_published' => $request->boolean('is_published'),
            'published_at' => $validated['published_at'] ?? null,
            'title' => ['ar' => $validated['title_ar'], 'en' => $validated['title_en'] ?? ''],
            'body' => ['ar' => $validated['body_ar'], 'en' => $validated['body_en'] ?? ''],
            'meta_description' => [
                'ar' => $validated['meta_description_ar'] ?? '',
                'en' => $validated['meta_description_en'] ?? '',
            ],
        ];
    }

    private function flushCache(?string $slug = null): void
    {
        Cache::forget('pages.list');
        if ($slug !== null) {
            Cache::forget("pages.show.{$slug}");
        }
    }
}
