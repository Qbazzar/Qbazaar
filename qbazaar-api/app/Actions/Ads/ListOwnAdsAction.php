<?php

declare(strict_types=1);

namespace App\Actions\Ads;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The seller's own ads for the My Ads screen, newest first, with the
 * per-ad conversation count the cards show next to views and favorites.
 */
class ListOwnAdsAction
{
    private const PER_PAGE = 20;

    /**
     * @return LengthAwarePaginator<int, Ad>
     */
    public function __invoke(User $seller, ?AdStatus $status): LengthAwarePaginator
    {
        return Ad::query()
            ->forUser($seller)
            ->when($status !== null, fn ($query) => $query->where('status', $status?->value))
            ->withCount('conversations')
            ->with(['category', 'location', 'media'])
            ->orderByDesc('created_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }
}
