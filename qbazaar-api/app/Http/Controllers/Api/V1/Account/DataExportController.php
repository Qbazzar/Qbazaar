<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Actions\Account\ClaimDataExportDownloadAction;
use App\Actions\Account\RequestDataExportAction;
use App\Http\Controllers\Controller;
use App\Models\DataExport;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * @group Account
 */
class DataExportController extends Controller
{
    /**
     * Queue a personal-data export for the signed-in user (one per 24 hours).
     * The user gets an email with a single-use download link when it is ready.
     *
     * @authenticated
     *
     * @response 202 scenario="Queued" {
     *   "success": true,
     *   "data": {
     *     "requested_at": "2026-05-23T10:00:00+03:00",
     *     "eta_minutes": 5,
     *     "status": "queued"
     *   }
     * }
     */
    public function request(Request $request, RequestDataExportAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->authorize('exportData', $user);

        $export = $action->execute($user);

        return response()->json([
            'requested_at' => $export->created_at->toIso8601String(),
            'eta_minutes' => 5,
            'status' => $export->status->value,
        ], 202);
    }

    /**
     * Download an export from the emailed link. The signed URL is the only
     * credential, so it opens in a browser without a bearer token; it works
     * once and the file is deleted after it is sent.
     *
     * @unauthenticated
     */
    public function download(string $id, ClaimDataExportDownloadAction $action): BinaryFileResponse
    {
        $export = $action->execute($id);

        return response()
            ->download(
                Storage::disk(DataExport::DISK)->path((string) $export->path),
                "qbazaar-data-export-{$export->id}.json",
                ['Content-Type' => 'application/json', 'Cache-Control' => 'no-store'],
            )
            ->deleteFileAfterSend();
    }
}
