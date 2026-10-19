<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manage\Catalog\BulkDestroyTaxonomyRequest;
use App\Http\Requests\Manage\Catalog\SaveCategoryRequest;
use App\Http\Requests\Manage\Catalog\UpdateTaxonomyStatusRequest;
use App\Models\Category;
use App\Models\User;
use App\Services\Admin\Catalog\CategoryTreeBuilder;
use App\Services\Admin\Catalog\DeleteBlockedNotice;
use App\Services\Catalog\CategoryFields;
use App\Services\Catalog\Taxonomy\TaxonomyDeletion;
use App\Services\Catalog\Taxonomy\TaxonomyKind;
use App\Services\Catalog\Taxonomy\TaxonomyTreeOptions;
use App\Services\Catalog\Taxonomy\TaxonomyUsageCounter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request, CategoryTreeBuilder $tree): View
    {
        $search = $request->string('q')->trim()->toString();
        $page = max(1, $request->integer('page', 1));
        $locale = app()->getLocale();

        $rows = $search === '' ? $tree->tree($page, $locale) : $tree->search($search, $page, $locale);

        return view('admin.categories.index', [
            'rows' => $rows->withQueryString(),
            'search' => $search,
            'blocked' => DeleteBlockedNotice::fromSession($request->session()),
        ]);
    }

    public function create(TaxonomyTreeOptions $options): View
    {
        return view('admin.categories.form', [
            'category' => new Category,
            'parents' => $options->for(TaxonomyKind::CATEGORY, null, app()->getLocale()),
            'usage' => null,
            'schema' => null,
            'blocked' => null,
            'kind' => TaxonomyKind::CATEGORY,
        ]);
    }

    public function store(SaveCategoryRequest $request): RedirectResponse
    {
        Category::query()->create($request->categoryData());

        return redirect()
            ->route('admin.categories.index')
            ->with('status', __('admin.catalog.flash.category_created'));
    }

    public function edit(Request $request, Category $category, TaxonomyTreeOptions $options, TaxonomyUsageCounter $counter, CategoryFields $fields): View
    {
        return view('admin.categories.form', [
            'category' => $category,
            'parents' => $options->for(TaxonomyKind::CATEGORY, $category->id, app()->getLocale()),
            // A side-card figure: if it fails the form still opens and the card says so.
            'usage' => rescue(fn () => $counter->forNode($category)),
            'schema' => $fields->forCategory($category),
            'blocked' => DeleteBlockedNotice::fromSession($request->session()),
            'kind' => TaxonomyKind::CATEGORY,
        ]);
    }

    public function update(SaveCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->categoryData());

        return redirect()
            ->route('admin.categories.index')
            ->with('status', __('admin.catalog.flash.category_updated'));
    }

    public function destroy(Category $category, TaxonomyDeletion $deletion): RedirectResponse
    {
        $usage = $deletion->delete($category);

        if ($usage->inUse()) {
            return back()
                ->with('error', $usage->blockedMessage(TaxonomyKind::CATEGORY, $category->getLocalizedName(app()->getLocale())))
                ->with(DeleteBlockedNotice::SESSION_KEY, DeleteBlockedNotice::payload($category, $usage));
        }

        return redirect()
            ->route('admin.categories.index')
            ->with('status', __('admin.catalog.flash.category_deleted'));
    }

    public function bulkDestroy(BulkDestroyTaxonomyRequest $request, TaxonomyDeletion $deletion): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();
        $result = $deletion->deleteMany(TaxonomyKind::CATEGORY, $request->ids(), $admin);

        return back()->with($result->hasBlocked() ? 'error' : 'status', $result->message());
    }

    public function status(UpdateTaxonomyStatusRequest $request, Category $category): RedirectResponse
    {
        $category->update(['is_active' => $request->active()]);

        return back()->with('status', __($request->active() ? 'admin.catalog.flash.category_activated' : 'admin.catalog.flash.category_deactivated'));
    }
}
