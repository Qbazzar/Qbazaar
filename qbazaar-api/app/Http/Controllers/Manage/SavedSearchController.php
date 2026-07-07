<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\SavedSearch;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SavedSearchController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->toString();

        $savedSearches = SavedSearch::query()
            ->with(['user:id,full_name,email'])
            ->when(
                $search !== '',
                fn ($query) => $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($user) => $user->where('full_name', 'like', "%{$search}%"));
                }),
            )
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.saved-searches.index', [
            'savedSearches' => $savedSearches,
            'search' => $search,
        ]);
    }
}
