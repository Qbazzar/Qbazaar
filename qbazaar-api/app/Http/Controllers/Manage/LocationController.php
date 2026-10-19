<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Enums\LocationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manage\Catalog\BulkDestroyTaxonomyRequest;
use App\Http\Requests\Manage\Catalog\SaveLocationRequest;
use App\Http\Requests\Manage\Catalog\UpdateTaxonomyStatusRequest;
use App\Models\Location;
use App\Models\User;
use App\Services\Admin\Catalog\DeleteBlockedNotice;
use App\Services\Catalog\Taxonomy\TaxonomyDeletion;
use App\Services\Catalog\Taxonomy\TaxonomyKind;
use App\Services\Catalog\Taxonomy\TaxonomyTreeOptions;
use App\Services\Catalog\Taxonomy\TaxonomyUsageCounter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LocationController extends Controller
{
    private const STATUSES = ['active', 'inactive'];

    public function index(Request $request): View
    {
        $search = $request->string('q')->toString();
        $type = $request->string('type')->toString();
        $status = $request->string('status')->toString();

        $locations = Location::query()
            ->with('parent:id,slug,name')
            ->when(
                in_array($type, array_column(LocationType::cases(), 'value'), true),
                fn ($query) => $query->where('type', $type),
            )
            ->when(
                in_array($status, self::STATUSES, true),
                fn ($query) => $query->where('is_active', $status === 'active'),
            )
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

        return view('admin.locations.index', [
            'locations' => $locations,
            'search' => $search,
            'type' => $type,
            'types' => LocationType::cases(),
            'status' => $status,
            'statuses' => self::STATUSES,
            'blocked' => DeleteBlockedNotice::fromSession($request->session()),
        ]);
    }

    public function create(TaxonomyTreeOptions $options): View
    {
        return view('admin.locations.form', [
            'location' => new Location,
            'parents' => $options->for(TaxonomyKind::LOCATION, null, app()->getLocale()),
            'types' => LocationType::cases(),
            'usage' => null,
            'blocked' => null,
            'kind' => TaxonomyKind::LOCATION,
        ]);
    }

    public function store(SaveLocationRequest $request): RedirectResponse
    {
        Location::query()->create($request->locationData());

        return redirect()
            ->route('admin.locations.index')
            ->with('status', __('admin.catalog.flash.location_created'));
    }

    public function edit(Request $request, Location $location, TaxonomyTreeOptions $options, TaxonomyUsageCounter $counter): View
    {
        return view('admin.locations.form', [
            'location' => $location,
            'parents' => $options->for(TaxonomyKind::LOCATION, $location->id, app()->getLocale()),
            'types' => LocationType::cases(),
            // A side-card figure: if it fails the form still opens and the card says so.
            'usage' => rescue(fn () => $counter->forNode($location)),
            'blocked' => DeleteBlockedNotice::fromSession($request->session()),
            'kind' => TaxonomyKind::LOCATION,
        ]);
    }

    public function update(SaveLocationRequest $request, Location $location): RedirectResponse
    {
        $location->update($request->locationData());

        return redirect()
            ->route('admin.locations.index')
            ->with('status', __('admin.catalog.flash.location_updated'));
    }

    public function destroy(Location $location, TaxonomyDeletion $deletion): RedirectResponse
    {
        $usage = $deletion->delete($location);

        if ($usage->inUse()) {
            return back()
                ->with('error', $usage->blockedMessage(TaxonomyKind::LOCATION, $location->getLocalizedName(app()->getLocale())))
                ->with(DeleteBlockedNotice::SESSION_KEY, DeleteBlockedNotice::payload($location, $usage));
        }

        return redirect()
            ->route('admin.locations.index')
            ->with('status', __('admin.catalog.flash.location_deleted'));
    }

    public function bulkDestroy(BulkDestroyTaxonomyRequest $request, TaxonomyDeletion $deletion): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();
        $result = $deletion->deleteMany(TaxonomyKind::LOCATION, $request->ids(), $admin);

        return back()->with($result->hasBlocked() ? 'error' : 'status', $result->message());
    }

    public function status(UpdateTaxonomyStatusRequest $request, Location $location): RedirectResponse
    {
        $location->update(['is_active' => $request->active()]);

        return back()->with('status', __($request->active() ? 'admin.catalog.flash.location_activated' : 'admin.catalog.flash.location_deactivated'));
    }
}
