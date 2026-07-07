<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Enums\OfferStatus;
use App\Http\Controllers\Controller;
use App\Models\Offer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OfferController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $search = $request->string('q')->toString();

        $offers = Offer::query()
            ->with(['ad:id,title', 'buyer:id,full_name', 'seller:id,full_name'])
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
        ]);
    }
}
