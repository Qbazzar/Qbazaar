<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Enums\LocationType;
use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->toString();
        $type = $request->string('type')->toString();

        $locations = Location::query()
            ->with('parent')
            ->when(
                in_array($type, array_column(LocationType::cases(), 'value'), true),
                fn ($query) => $query->where('type', $type),
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

        return view('manage.locations.index', [
            'locations' => $locations,
            'search' => $search,
            'type' => $type,
            'types' => LocationType::cases(),
        ]);
    }

    public function create(): View
    {
        return view('manage.locations.form', [
            'location' => new Location,
            'parents' => $this->parentOptions(),
            'types' => LocationType::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Location::create($this->validated($request));
        $this->flushCache();

        return redirect()
            ->route('manage.locations.index')
            ->with('status', 'تم إنشاء الموقع بنجاح.');
    }

    public function edit(Location $location): View
    {
        return view('manage.locations.form', [
            'location' => $location,
            'parents' => $this->parentOptions($location->id),
            'types' => LocationType::cases(),
        ]);
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        $location->update($this->validated($request, $location));
        $this->flushCache();

        return redirect()
            ->route('manage.locations.index')
            ->with('status', 'تم تحديث الموقع بنجاح.');
    }

    public function destroy(Location $location): RedirectResponse
    {
        $location->delete();
        $this->flushCache();

        return redirect()
            ->route('manage.locations.index')
            ->with('status', 'تم حذف الموقع.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Location $location = null): array
    {
        $validated = $request->validate([
            'name.ar' => ['required', 'string', 'max:255'],
            'name.en' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:64',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('locations', 'slug')->ignore($location?->id),
            ],
            'type' => ['required', new Enum(LocationType::class)],
            'parent_id' => [
                'nullable',
                'string',
                Rule::exists('locations', 'id'),
                Rule::notIn(array_filter([$location?->id])),
            ],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'order' => ['required', 'integer', 'min:0'],
        ]);

        return [
            'name' => ['ar' => $validated['name']['ar'], 'en' => $validated['name']['en']],
            'slug' => $validated['slug'],
            'type' => $validated['type'],
            'parent_id' => $validated['parent_id'] ?? null,
            'lat' => $validated['lat'] ?? null,
            'lng' => $validated['lng'] ?? null,
            'order' => $validated['order'],
        ];
    }

    /**
     * @return Collection<int, Location>
     */
    private function parentOptions(?string $excludeId = null)
    {
        return Location::query()
            ->when($excludeId !== null, fn ($query) => $query->whereKeyNot($excludeId))
            ->orderBy('order')
            ->get();
    }

    private function flushCache(): void
    {
        Cache::forget('locations.qatar');
    }
}
