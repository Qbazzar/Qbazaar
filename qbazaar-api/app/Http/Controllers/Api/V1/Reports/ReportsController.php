<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reports;

use App\Actions\Reports\MakeReportAction;
use App\Enums\ReportCategory;
use App\Enums\ReportTarget;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Reports\MakeReportRequest;
use App\Http\Resources\Api\V1\Reports\ReportResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Abuse-reports submission endpoint.
 *
 * Single endpoint by design — there is intentionally no `index` or `show`
 * surfaced to the public API. Listing/inspection is admin-only and lives
 * in Sprint 11's Filament resource. Surfacing a listing here would leak
 * the rate of incoming reports to abusers.
 *
 * @group Reports
 */
class ReportsController extends Controller
{
    public function __construct(
        private readonly MakeReportAction $action,
    ) {}

    /**
     * POST /api/v1/reports — file a report.
     *
     * The action emits domain errors (self-report, duplicate, missing
     * target) — those are caught by the global exception renderer and
     * shaped into the standard error envelope.
     *
     * @authenticated
     */
    public function store(MakeReportRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array{target_type: string, target_id: string, category: string, description?: ?string} $data */
        $data = $request->validated();

        $report = $this->action->execute(
            $user,
            ReportTarget::from($data['target_type']),
            $data['target_id'],
            ReportCategory::from($data['category']),
            $data['description'] ?? null,
        );

        return response()
            ->json((new ReportResource($report))->toArray($request))
            ->setStatusCode(SymfonyResponse::HTTP_CREATED);
    }
}
