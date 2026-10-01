<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Actions\Ads\ToggleAdFeaturedAction;
use App\Enums\AdStatus;
use App\Enums\Condition;
use App\Enums\PriceType;
use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Category;
use App\Models\Location;
use App\Rules\NoMarkup;
use App\Services\Ads\AdLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

class AdController extends Controller
{
    public function __construct(private readonly AdLifecycleService $lifecycle) {}

    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $search = $request->string('q')->toString();

        $ads = Ad::query()
            ->with(['user', 'category'])
            ->when(
                in_array($status, array_column(AdStatus::cases(), 'value'), true),
                fn ($query) => $query->where('status', $status),
            )
            ->when(
                $search !== '',
                fn ($query) => $query->where(function ($inner) use ($search): void {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('id', $search);
                }),
            )
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.ads.index', [
            'ads' => $ads,
            'status' => $status,
            'search' => $search,
            'statuses' => AdStatus::cases(),
        ]);
    }

    public function show(Ad $ad): View
    {
        $ad->load(['user', 'category', 'location', 'media']);

        return view('admin.ads.show', ['ad' => $ad]);
    }

    public function edit(Ad $ad): View
    {
        return view('admin.ads.edit', [
            'ad' => $ad,
            'categories' => $this->categoryOptions(),
            'locations' => $this->locationOptions(),
            'priceTypes' => PriceType::cases(),
            'conditions' => Condition::cases(),
        ]);
    }

    public function update(Request $request, Ad $ad): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255', new NoMarkup],
            'description' => ['required', 'string', new NoMarkup],
            'category_id' => ['required', Rule::exists('categories', 'id')],
            'location_id' => ['required', Rule::exists('locations', 'id')],
            'price' => ['nullable', 'numeric', 'min:0'],
            'price_type' => ['required', new Enum(PriceType::class)],
            'condition' => ['nullable', new Enum(Condition::class)],
            'featured' => ['boolean'],
        ]);

        $ad->update([
            ...$data,
            'featured' => $request->boolean('featured'),
        ]);

        return redirect()
            ->route('admin.ads.show', $ad)
            ->with('status', 'تم حفظ تعديلات الإعلان.');
    }

    /** @return array<string, string> */
    private function categoryOptions(): array
    {
        return Category::query()
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (Category $c): array => [$c->id => $c->getLocalizedName(app()->getLocale())])
            ->all();
    }

    /** @return array<string, string> */
    private function locationOptions(): array
    {
        return Location::query()
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (Location $l): array => [$l->id => $l->getLocalizedName(app()->getLocale())])
            ->all();
    }

    public function approve(Ad $ad): RedirectResponse
    {
        $this->lifecycle->approve($ad);

        return back()->with('status', __('admin.actions.ad_approved'));
    }

    public function reject(Request $request, Ad $ad): RedirectResponse
    {
        $data = $request->validate([
            'admin_notes' => ['required', 'string', 'max:1000'],
        ]);

        $this->lifecycle->reject($ad, $data['admin_notes']);

        return back()->with('status', __('admin.actions.ad_rejected'));
    }

    public function suspend(Ad $ad): RedirectResponse
    {
        $this->lifecycle->block($ad);

        return back()->with('status', __('admin.actions.ad_suspended'));
    }

    public function unsuspend(Ad $ad): RedirectResponse
    {
        $this->lifecycle->unblock($ad);

        return back()->with('status', __('admin.actions.ad_unsuspended'));
    }

    public function toggleFeature(Ad $ad, ToggleAdFeaturedAction $toggleFeatured): RedirectResponse
    {
        $featured = $toggleFeatured($ad);

        return back()->with('status', $featured ? 'تم تمييز الإعلان.' : 'تم إلغاء تمييز الإعلان.');
    }

    public function forceExpire(Ad $ad): RedirectResponse
    {
        $this->lifecycle->expire($ad);

        return back()->with('status', 'تم إنهاء الإعلان.');
    }

    public function destroy(Ad $ad): RedirectResponse
    {
        $ad->delete();

        return redirect()
            ->route('admin.ads.index')
            ->with('status', 'تم حذف الإعلان.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:' . config('qbazaar.admin.bulk_action_max')],
            'ids.*' => ['required', 'string', 'ulid'],
        ]);

        // A mass query delete skips model events, leaving the ads searchable and
        // unlogged; deleting each model matches the single-delete path.
        $ads = Ad::query()->with('user')->whereIn('id', $data['ids'])->get();
        DB::transaction(fn () => $ads->each->delete());
        $count = $ads->count();

        return back()->with('status', "تم حذف {$count} إعلاناً.");
    }
}
