<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manage\ReorderPositionsRequest;
use App\Models\User;
use App\Services\Business\FeaturedCompanies;
use App\Services\Business\FeaturedCompanyCurator;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The featured companies in the order they lead the companies directory and
 * the home screen. Staff type a position next to each one to reorder them.
 */
class FeaturedCompanyController extends Controller
{
    public function index(#[CurrentUser] User $actor, FeaturedCompanies $featured): View
    {
        return view('admin.companies.featured', [
            'companies' => $featured->forAdmin(),
            'max' => $featured->max(),
            'canFeature' => $actor->can('companies.feature'),
        ]);
    }

    public function reorder(ReorderPositionsRequest $request, #[CurrentUser] User $actor, FeaturedCompanyCurator $curator): RedirectResponse
    {
        $curator->reorder($actor, $request->positions());

        return redirect()->route('admin.companies.featured.index')->with('status', __('admin.companies.flash.reordered'));
    }
}
