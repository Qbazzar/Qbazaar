<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manage\Finance\RevenueReportRequest;
use App\Models\User;
use App\Services\Admin\AdminAuditLogger;
use App\Services\Finance\Revenue\RevenueCsvExporter;
use App\Services\Finance\Revenue\RevenuePresenter;
use App\Services\Finance\Revenue\RevenueReportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The platform's income statement: income, deductions and net profit for a
 * period. Both actions need finance.view; the figures come from the ledger.
 */
class RevenueController extends Controller
{
    private const int LINES_PER_PAGE = 20;

    public function index(RevenueReportRequest $request, RevenueReportService $service): View
    {
        /** @var User $user */
        $user = $request->user();
        $window = $request->window();
        $report = $service->report($window, $user->can('users.view'));
        $lines = $service->latestLines($window, $report->revenueAccountId, $request->page(), self::LINES_PER_PAGE);

        return view('admin.finance.revenue.index', [
            'revenue' => new RevenuePresenter($report, $lines->withQueryString(), $user),
            'range' => $request->range(),
        ]);
    }

    public function export(RevenueReportRequest $request, RevenueCsvExporter $csv, AdminAuditLogger $audit): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();
        $window = $request->window();

        $audit->record($user, 'finance.revenue_exported', null, [
            'range' => $window->range->value,
            'from' => $window->start->toDateString(),
            'to' => $window->end->subSecond()->toDateString(),
        ]);

        return $csv->download($window);
    }
}
