<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\SavedSearch;
use Illuminate\View\View;

class SavedSearchController extends Controller
{
    public function index(): View
    {
        $savedSearches = SavedSearch::query()
            ->with(['user:id,full_name,email'])
            ->latest()
            ->paginate(20);

        return view('admin.saved-searches.index', ['savedSearches' => $savedSearches]);
    }
}
