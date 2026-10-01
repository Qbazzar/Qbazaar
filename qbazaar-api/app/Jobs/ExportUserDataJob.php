<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\DataExportStatus;
use App\Models\DataExport;
use App\Models\User;
use App\Notifications\DataExportReadyNotification;
use App\Services\Account\UserDataExporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use RuntimeException;

/**
 * Builds a requested personal-data export and emails the owner a signed,
 * single-use download link that opens straight from the browser.
 *
 * Idempotent: an export that is already ready is not rebuilt or re-sent.
 */
class ExportUserDataJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(
        public readonly string $exportId,
    ) {
        $this->onQueue('low');
    }

    public function handle(UserDataExporter $exporter): void
    {
        $export = DataExport::query()->with('user')->find($this->exportId);
        $user = $export?->user;

        if ($export === null || $user === null || $export->status === DataExportStatus::READY) {
            return;
        }

        $path = "exports/{$export->id}.json";
        $this->writeFile($exporter, $user, $export->id, $path);

        $hours = (int) config('qbazaar.account.data_export_link_ttl_hours');
        $expiresAt = Carbon::now()->addHours($hours);

        $export->forceFill([
            'status' => DataExportStatus::READY,
            'path' => $path,
            'expires_at' => $expiresAt,
        ])->save();

        $user->notify(new DataExportReadyNotification(
            downloadUrl: URL::temporarySignedRoute('api.v1.account.data-export.download', $expiresAt, ['id' => $export->id]),
            expiresInHours: $hours,
        ));
    }

    private function writeFile(UserDataExporter $exporter, User $user, string $exportId, string $path): void
    {
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            throw new RuntimeException('Could not open a buffer for the data export.');
        }

        try {
            $exporter->write($stream, $user, $exportId);
            rewind($stream);

            if (! Storage::disk(DataExport::DISK)->writeStream($path, $stream)) {
                throw new RuntimeException("Could not store the data export {$exportId}.");
            }
        } finally {
            fclose($stream);
        }
    }
}
