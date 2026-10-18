<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Enums\OfferStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manage\Moderation\SanctionRequest;
use App\Models\Ad;
use App\Models\Offer;
use App\Models\User;
use App\Services\Admin\Offers\OfferDetail;
use App\Services\Moderation\OfferCancellation;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OfferController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $search = $request->string('q')->toString();
        $adId = Str::isUlid($request->string('ad')->toString()) ? $request->string('ad')->toString() : '';

        $offers = Offer::query()
            ->with(['ad:id,title', 'buyer:id,full_name', 'seller:id,full_name', 'buyer.avatarMedia', 'seller.avatarMedia'])
            // Served by offers_ad_created_idx.
            ->when($adId !== '', fn ($query) => $query->where('ad_id', $adId))
            ->when(
                in_array($status, array_column(OfferStatus::cases(), 'value'), true),
                fn ($query) => $query->where('status', $status),
            )
            ->when(
                $search !== '',
                fn ($query) => $query->where(function ($inner) use ($search): void {
                    $inner->whereHas('ad', fn ($ad) => $ad->where('title', 'like', "%{$search}%"))
                        ->orWhereHas('buyer', fn ($user) => $user->where('full_name', 'like', "%{$search}%"));
                }),
            )
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.offers.index', [
            'offers' => $offers,
            'status' => $status,
            'search' => $search,
            'statuses' => OfferStatus::cases(),
            'adId' => $adId,
            'adFilter' => $adId === '' ? null : Ad::withTrashed()->find($adId, ['id', 'title']),
        ]);
    }

    public function show(#[CurrentUser] User $viewer, Offer $offer, OfferDetail $detail): View
    {
        return view('admin.offers.show', $detail->forShow($offer, $viewer));
    }

    /** Close an open offer with a reason; disputes on accepted offers are ruled through the finance flow. */
    public function cancel(SanctionRequest $request, #[CurrentUser] User $staff, Offer $offer, OfferCancellation $cancellation): RedirectResponse
    {
        $cancellation->cancel($staff, $offer, $request->sanction());

        return redirect()
            ->route('admin.offers.show', $offer)
            ->with('status', __('admin.offer_admin.flash.cancelled'));
    }
}
