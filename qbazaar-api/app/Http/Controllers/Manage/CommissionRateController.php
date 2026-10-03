<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Actions\Admin\SetCategoryCommissionRateAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manage\SetCategoryCommissionRateRequest;
use App\Models\Category;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Per-category commission overrides, edited on the platform settings page.
 */
class CommissionRateController extends Controller
{
    public function store(SetCategoryCommissionRateRequest $request, SetCategoryCommissionRateAction $rates): RedirectResponse
    {
        /** @var array{category_id: string, rate: int|string} $data */
        $data = $request->validated();

        $rates->set($data['category_id'], (string) $data['rate'], $this->actor($request));

        return redirect()->route('admin.settings.edit')->with('status', __('admin.commission_rates.saved'));
    }

    public function destroy(Request $request, Category $category, SetCategoryCommissionRateAction $rates): RedirectResponse
    {
        $rates->clear($category->id, $this->actor($request));

        return redirect()->route('admin.settings.edit')->with('status', __('admin.commission_rates.removed'));
    }

    private function actor(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
