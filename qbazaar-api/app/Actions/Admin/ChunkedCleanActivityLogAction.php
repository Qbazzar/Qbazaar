<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use Spatie\Activitylog\Actions\CleanActivityLogAction;
use Spatie\Activitylog\Support\Config;

/**
 * `activitylog:clean` deletes everything past the cutoff in one statement;
 * on a large table that holds row locks and undo for the whole run. Deleting
 * in bounded batches keeps every statement short.
 */
class ChunkedCleanActivityLogAction extends CleanActivityLogAction
{
    protected function deleteOldActivities(string $cutOffDate, ?string $logName): int
    {
        $activity = Config::activityModelInstance();
        $chunk = max(1, (int) config('qbazaar.retention.prune_chunk'));
        $total = 0;

        do {
            $deleted = $activity::query()
                ->where('created_at', '<', $cutOffDate)
                ->when($logName !== null, fn ($query) => $query->where('log_name', $logName))
                ->limit($chunk)
                ->delete();

            $total += $deleted;
        } while ($deleted > 0);

        return $total;
    }
}
