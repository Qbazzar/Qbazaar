<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\HelpArticle;
use App\Models\HelpCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HelpArticleController extends Controller
{
    public function index(): View
    {
        $articles = HelpArticle::query()
            ->with('category')
            ->orderBy('display_order')
            ->orderBy('id')
            ->paginate(20);

        return view('admin.help-articles.index', ['articles' => $articles]);
    }

    public function create(): View
    {
        return view('admin.help-articles.form', [
            'article' => new HelpArticle,
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        HelpArticle::create($this->validated($request));
        $this->flushCache();

        return redirect()
            ->route('admin.help-articles.index')
            ->with('status', 'تم إنشاء المقال بنجاح.');
    }

    public function edit(HelpArticle $help_article): View
    {
        return view('admin.help-articles.form', [
            'article' => $help_article,
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function update(Request $request, HelpArticle $help_article): RedirectResponse
    {
        $help_article->update($this->validated($request, $help_article));
        $this->flushCache();

        return redirect()
            ->route('admin.help-articles.index')
            ->with('status', 'تم تحديث المقال بنجاح.');
    }

    public function destroy(HelpArticle $help_article): RedirectResponse
    {
        $help_article->delete();
        $this->flushCache();

        return redirect()
            ->route('admin.help-articles.index')
            ->with('status', 'تم حذف المقال.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['string'],
        ]);

        $count = HelpArticle::whereIn('id', $data['ids'])->delete();
        $this->flushCache();

        return back()->with('status', "تم حذف {$count} عنصراً.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?HelpArticle $article = null): array
    {
        $validated = $request->validate([
            'category_id' => ['required', 'string', Rule::exists('help_categories', 'id')],
            'slug' => ['required', 'string', 'max:120', 'alpha_dash', Rule::unique('help_articles', 'slug')->ignore($article?->id)],
            'display_order' => ['required', 'integer', 'min:0'],
            'is_published' => ['nullable', 'boolean'],
            'title_ar' => ['required', 'string', 'max:255'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'body_ar' => ['required', 'string'],
            'body_en' => ['nullable', 'string'],
            'excerpt_ar' => ['nullable', 'string', 'max:500'],
            'excerpt_en' => ['nullable', 'string', 'max:500'],
        ]);

        return [
            'category_id' => $validated['category_id'],
            'slug' => $validated['slug'],
            'display_order' => $validated['display_order'],
            'is_published' => $request->boolean('is_published'),
            'title' => ['ar' => $validated['title_ar'], 'en' => $validated['title_en'] ?? ''],
            'body' => ['ar' => $validated['body_ar'], 'en' => $validated['body_en'] ?? ''],
            'excerpt' => [
                'ar' => $validated['excerpt_ar'] ?? '',
                'en' => $validated['excerpt_en'] ?? '',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function categoryOptions(): array
    {
        return HelpCategory::query()
            ->orderBy('display_order')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (HelpCategory $c): array => [$c->id => ($c->name['ar'] ?? $c->slug) . " ({$c->slug})"])
            ->all();
    }

    private function flushCache(): void
    {
        Cache::forget('help.categories');
    }
}
