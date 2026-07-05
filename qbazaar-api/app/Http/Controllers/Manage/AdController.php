<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Enums\AdStatus;
use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Services\Ads\AdModerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdController extends Controller
{
    public function __construct(private readonly AdModerationService $moderation) {}

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

        return view('manage.ads.index', [
            'ads' => $ads,
            'status' => $status,
            'search' => $search,
            'statuses' => AdStatus::cases(),
        ]);
    }

    public function show(Ad $ad): View
    {
        $ad->load(['user', 'category', 'location', 'media']);

        return view('manage.ads.show', ['ad' => $ad]);
    }

    public function approve(Ad $ad): RedirectResponse
    {
        $this->moderation->approve($ad);

        return back()->with('status', __('admin.actions.ad_approved'));
    }

    public function reject(Request $request, Ad $ad): RedirectResponse
    {
        $data = $request->validate([
            'admin_notes' => ['required', 'string', 'max:1000'],
        ]);

        $this->moderation->reject($ad, $data['admin_notes']);

        return back()->with('status', __('admin.actions.ad_rejected'));
    }

    public function suspend(Ad $ad): RedirectResponse
    {
        $this->moderation->suspend($ad);

        return back()->with('status', __('admin.actions.ad_suspended'));
    }

    public function unsuspend(Ad $ad): RedirectResponse
    {
        $this->moderation->unsuspend($ad);

        return back()->with('status', __('admin.actions.ad_unsuspended'));
    }

    public function toggleFeature(Ad $ad): RedirectResponse
    {
        $featured = $this->moderation->toggleFeature($ad);

        return back()->with('status', $featured ? 'تم تمييز الإعلان.' : 'تم إلغاء تمييز الإعلان.');
    }

    public function forceExpire(Ad $ad): RedirectResponse
    {
        $this->moderation->forceExpire($ad);

        return back()->with('status', 'تم إنهاء الإعلان.');
    }

    public function destroy(Ad $ad): RedirectResponse
    {
        $ad->delete();

        return redirect()
            ->route('manage.ads.index')
            ->with('status', 'تم حذف الإعلان.');
    }
}
