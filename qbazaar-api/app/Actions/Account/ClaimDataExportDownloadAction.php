<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Enums\DataExportStatus;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\DataExport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Spends the single use of an export's download link.
 *
 * The conditional update is the claim: of two concurrent requests for the
 * same link only one changes the row, so the file is handed out once.
 */
class ClaimDataExportDownloadAction
{
    public function execute(string $exportId): DataExport
    {
        $claimed = DataExport::query()
            ->whereKey($exportId)
            ->where('status', DataExportStatus::READY->value)
            ->whereNull('downloaded_at')
            ->where('expires_at', '>', Carbon::now())
            ->update(['downloaded_at' => Carbon::now()]);

        $export = $claimed === 1 ? DataExport::query()->find($exportId) : null;

        if ($export === null || $export->path === null || ! Storage::disk(DataExport::DISK)->exists($export->path)) {
            throw new DomainException(ErrorCode::DATA_EXPORT_LINK_EXPIRED);
        }

        return $export;
    }
}
