<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Notifications\DatabaseNotification as BaseDatabaseNotification;
use Illuminate\Support\Carbon;

/**
 * Exists so `model:prune` can reach the framework's notifications table.
 * Unread rows are never pruned: they are still waiting for the user.
 */
class DatabaseNotification extends BaseDatabaseNotification
{
    use MassPrunable;

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::query()->where(
            'read_at',
            '<',
            Carbon::now()->subDays((int) config('qbazaar.retention.read_notifications_days')),
        );
    }
}
