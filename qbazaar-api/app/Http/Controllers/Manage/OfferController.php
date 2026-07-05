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

        $offers = Offer::query()
            ->with(['ad:id,title', 'buyer:id,full_name', 'seller:id,full_name'])
            ->when(
                in_array($status, array_column(OfferStatus::cases(), 'value'), true),
                fn ($query) => $query->where('status', $status),
            )
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('manage.offers.index', [
            'offers' => $offers,
            'status' => $status,
            'statuses' => OfferStatus::cases(),
        ]);
    }
}
