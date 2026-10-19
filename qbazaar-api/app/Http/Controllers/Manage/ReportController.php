<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Actions\Reports\ResolveReportAction;
use App\Enums\ReportCategory;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manage\Moderation\SanctionFromReportRequest;
use App\Models\Report;
use App\Models\User;
use App\Services\Admin\Reports\ReportDetailPanels;
use App\Services\Moderation\ReportSanctions;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(private readonly ResolveReportAction $resolveReport) {}

    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $category = $request->string('category')->toString();
        $search = $request->string('q')->toString();

        $reports = Report::query()
            ->with(['reporter:id,full_name,email', 'reviewer:id,full_name', 'reporter.avatarMedia', 'reviewer.avatarMedia'])
            ->when(
                in_array($status, array_column(ReportStatus::cases(), 'value'), true),
                fn ($query) => $query->where('status', $status),
            )
            ->when(
                in_array($category, array_column(ReportCategory::cases(), 'value'), true),
                fn ($query) => $query->where('category', $category),
            )
            ->when(
                $search !== '',
                fn ($query) => $query->where(function ($inner) use ($search): void {
                    $inner->where('target_id', $search)
                        ->orWhere('id', $search)
                        ->orWhereHas('reporter', fn ($r) => $r->where('full_name', 'like', "%{$search}%"));
                }),
            )
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.reports.index', [
            'reports' => $reports,
            'status' => $status,
            'category' => $category,
            'search' => $search,
            'statuses' => ReportStatus::cases(),
            'categories' => ReportCategory::cases(),
        ]);
    }

    public function show(#[CurrentUser] User $viewer, Report $report, ReportDetailPanels $panels): View
    {
        return view('admin.reports.show', $panels->forShow($report, $viewer));
    }

    public function resolve(Report $report): RedirectResponse
    {
        $this->transition($report, ReportStatus::REVIEWED);

        return back()->with('status', __('admin.flash.report_resolved'));
    }

    public function dismiss(Report $report): RedirectResponse
    {
        $this->transition($report, ReportStatus::DISMISSED);

        return back()->with('status', __('admin.flash.report_dismissed'));
    }

    public function action(Request $request, Report $report): RedirectResponse
    {
        $data = $request->validate([
            'admin_notes' => ['required', 'string', 'max:1000'],
        ]);

        $this->transition($report, ReportStatus::ACTIONED, $data['admin_notes']);

        return back()->with('status', __('admin.flash.report_actioned'));
    }

    /** Bulk-dismiss the selected pending reports. */
    public function bulkDismiss(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:' . (int) config('qbazaar.admin.bulk_action_max')],
            'ids.*' => ['string'],
        ]);

        $count = 0;
        Report::whereIn('id', $data['ids'])
            ->where('status', ReportStatus::PENDING->value)
            ->get()
            ->each(function (Report $report) use (&$count): void {
                $this->transition($report, ReportStatus::DISMISSED);
                $count++;
            });

        return back()->with('status', __('admin.flash.reports_dismissed', ['count' => $count]));
    }

    /**
     * Sanction the report's target (suspend or remove the ad, suspend the
     * account) with a reason, and close the report as actioned, in one step.
     */
    public function sanction(
        SanctionFromReportRequest $request,
        #[CurrentUser]
        User $actor,
        Report $report,
        ReportSanctions $sanctions,
    ): RedirectResponse {
        $action = $request->action();
        $sanctions->apply($actor, $report, $action, $request->sanction());

        return redirect()
            ->route('admin.reports.show', $report)
            ->with('status', __('admin.moderation.flash.report_sanctioned', ['action' => $action->label()]));
    }

    private function transition(Report $report, ReportStatus $status, ?string $notes = null): void
    {
        /** @var User $staff */
        $staff = auth()->user();

        $this->resolveReport->execute($report, $status, $staff, $notes);
    }
}
