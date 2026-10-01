<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use Illuminate\Database\Eloquent\Model;
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

        // Config::activityModel() already guarantees an Eloquent model.
        if (! $activity instanceof Model) {
            return 0;
        }

        $chunk = max(1, (int) config('qbazaar.retention.prune_chunk'));
        $total = 0;

        do {
            $deleted = $activity->newQuery()
                ->where('created_at', '<', $cutOffDate)
                ->when($logName !== null, fn ($query) => $query->where('log_name', $logName))
                ->limit($chunk)
                ->delete();

            $total += $deleted;
        } while ($deleted > 0);

        return $total;
    }
}
