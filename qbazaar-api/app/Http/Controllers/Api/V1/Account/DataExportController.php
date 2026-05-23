<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Jobs\ExportUserDataJob;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @group Account
 */
class DataExportController extends Controller
{
    /**
     * Queue a GDPR-style data export for the signed-in user.
     *
     * We throttle to one export per user per 24h via Cache::lock so a
     * stampede of clicks can't fill the queue (and the user's mailbox).
     * On success the job is dispatched to the `low` queue and the user
     * gets a 202 with the request metadata.
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
    public function request(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->authorize('exportData', $user);

        // The lock doubles as the rate-limit window: hold it for 24h so a
        // second request inside the window short-circuits. The block-less
        // form (->get() with no callback) lets us inspect ownership and
        // respond cleanly instead of throwing LockTimeoutException.
        $lock = Cache::lock('account:data-export:' . $user->id, 24 * 3600);

        if (! $lock->get()) {
            throw new DomainException(
                ErrorCode::RATE_LIMIT_EXCEEDED,
                __('errors.rate.limit.exceeded'),
            );
        }

        $exportId = $user->id . '-' . Carbon::now()->format('YmdHis') . '-' . Str::random(8);
        $requestedAt = Carbon::now();

        ExportUserDataJob::dispatch($user->id, $exportId);

        return response()->json([
            'requested_at' => $requestedAt->toIso8601String(),
            'eta_minutes' => 5,
            'status' => 'queued',
        ], 202);
    }

    /**
     * Stream a previously-generated export to the requester.
     *
     * The route is signed (TTL = 48h) and additionally requires:
     *  - a Sanctum bearer for the owner,
     *  - the export id to start with `{user->id}-` so an attacker who
     *    somehow leaks another user's signed URL still can't download it.
     *
     * @authenticated
     */
    public function download(Request $request, string $id): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->authorize('exportData', $user);

        if (! str_starts_with($id, $user->id . '-')) {
            throw new DomainException(ErrorCode::USER_NOT_FOUND);
        }

        $path = "exports/{$id}.json";
        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            throw new DomainException(ErrorCode::USER_NOT_FOUND);
        }

        return $disk->download($path, "qbazaar-data-export-{$id}.json", [
            'Content-Type' => 'application/json',
        ]);
    }
}
