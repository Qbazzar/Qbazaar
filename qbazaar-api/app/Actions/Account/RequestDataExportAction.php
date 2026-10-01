<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Enums\DataExportStatus;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Jobs\ExportUserDataJob;
use App\Models\DataExport;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Records a data export request and queues the job that builds it.
 *
 * One export per user per 24 hours: the cache lock is held for the whole
 * window, so a burst of clicks queues a single job.
 */
class RequestDataExportAction
{
    private const int WINDOW_SECONDS = 24 * 3600;

    public function execute(User $user): DataExport
    {
        $lock = Cache::lock('account:data-export:' . $user->id, self::WINDOW_SECONDS);

        if (! $lock->get()) {
            throw new DomainException(ErrorCode::RATE_LIMIT_EXCEEDED, __('errors.rate.limit.exceeded'));
        }

        try {
            $export = new DataExport;
            $export->user_id = $user->id;
            $export->status = DataExportStatus::QUEUED;
            $export->save();
        } catch (Throwable $exception) {
            // Nothing was queued, so the user must be able to try again now.
            $lock->release();

            throw $exception;
        }

        ExportUserDataJob::dispatch($export->id)->afterCommit();

        return $export;
    }
}
