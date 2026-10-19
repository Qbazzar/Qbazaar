<?php

declare(strict_types=1);

namespace App\Services\Admin\Broadcasts;

use App\Enums\LocationType;
use App\Models\Location;
use App\Models\StaffBroadcast;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * What the broadcast page reads: the sent broadcasts, newest first on
 * staff_broadcasts_created_idx with their sender and city eager loaded, and
 * the cities a broadcast can target.
 */
class BroadcastHistory
{
    /**
     * @return LengthAwarePaginator<int, StaffBroadcast>
     */
    public function latest(): LengthAwarePaginator
    {
        return StaffBroadcast::query()
            ->with(['sender:id,full_name,email', 'sender.avatarMedia', 'location:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) config('qbazaar.admin.broadcasts.history_per_page'))
            ->withQueryString();
    }

    /**
     * @return Collection<int, Location>
     */
    public function cities(): Collection
    {
        return Location::query()
            ->where('type', LocationType::CITY->value)
            ->orderBy('order')
            ->get(['id', 'name']);
    }
}
