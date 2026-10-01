<?php

declare(strict_types=1);

namespace App\Observers\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

trait ResolvesActivityCauser
{
    /**
     * The signed-in user made the change (a staff member acting on someone
     * else's record included); only changes without one, such as queued
     * jobs, fall back to the record's owner.
     */
    private function causer(?User $owner): ?User
    {
        $actor = Auth::user();

        return $actor instanceof User ? $actor : $owner;
    }
}
