<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Enums\CategoryFieldType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manage\Catalog\SaveCategoryFieldRequest;
use App\Models\Category;
use App\Models\User;
use App\Services\Admin\Catalog\CategoryFieldForm;
use App\Services\Admin\Catalog\CategoryFieldForms;
use App\Services\Admin\Catalog\CategoryFieldsOverview;
use App\Services\Catalog\Fields\CategoryFieldEditor;
use App\Services\Catalog\Fields\FieldDefinitions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The fields editor of a category: the custom attributes ads in it carry,
 * declared here or inherited from the categories above it.
 */
class CategoryFieldController extends Controller
{
    public function __construct(private readonly CategoryFieldEditor $editor) {}

    public function index(Category $category, CategoryFieldsOverview $overview): View
    {
        return view('admin.categories.fields.index', [
            'category' => $category,
            'view' => $overview->for($category, app()->getLocale()),
        ]);
    }

    /**
     * `?override={key}` opens the form prefilled with an inherited field.
     */
    public function create(Request $request, Category $category, CategoryFieldForms $forms): View
    {
        $key = $request->string('override')->toString();
        $form = $key === '' ? $forms->blank($category) : $forms->override($category, $key);

        abort_if($form === null, 404);

        return $this->form($category, $form);
    }

    public function store(SaveCategoryFieldRequest $request, Category $category): RedirectResponse
    {
        $this->editor->save($category, $request->definition(), $this->admin($request));

        return redirect()
            ->route('admin.categories.fields.index', $category)
            ->with('status', __('admin.catalog.fields.flash.saved'));
    }

    public function edit(Category $category, string $field, CategoryFieldForms $forms): View
    {
        $form = $forms->edit($category, $field);

        abort_if($form === null, 404);

        return $this->form($category, $form);
    }

    public function update(SaveCategoryFieldRequest $request, Category $category, string $field): RedirectResponse
    {
        $own = $this->editor->ownEntry($category, $field);

        abort_if($own === null || FieldDefinitions::isHidden($own), 404);

        $this->editor->save($category, $request->definition(), $this->admin($request));

        return redirect()
            ->route('admin.categories.fields.index', $category)
            ->with('status', __('admin.catalog.fields.flash.saved'));
    }

    /**
     * Removes the category's own entry: its definition, or the marker that
     * hides an inherited field.
     */
    public function destroy(Request $request, Category $category, string $field): RedirectResponse
    {
        abort_unless($this->editor->remove($category, $field, $this->admin($request)), 404);

        return redirect()
            ->route('admin.categories.fields.index', $category)
            ->with('status', __('admin.catalog.fields.flash.removed'));
    }

    public function hide(Request $request, Category $category, string $field): RedirectResponse
    {
        abort_unless($this->editor->hide($category, $field, $this->admin($request)), 404);

        return redirect()
            ->route('admin.categories.fields.index', $category)
            ->with('status', __('admin.catalog.fields.flash.hidden'));
    }

    private function form(Category $category, CategoryFieldForm $form): View
    {
        return view('admin.categories.fields.form', [
            'category' => $category,
            'form' => $form,
            'types' => CategoryFieldType::cases(),
        ]);
    }

    private function admin(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
