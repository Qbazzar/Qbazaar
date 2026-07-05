<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Enums\ReportCategory;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $category = $request->string('category')->toString();
        $search = $request->string('q')->toString();

        $reports = Report::query()
            ->with(['reporter:id,full_name,email', 'reviewer:id,full_name'])
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

        return view('manage.reports.index', [
            'reports' => $reports,
            'status' => $status,
            'category' => $category,
            'search' => $search,
            'statuses' => ReportStatus::cases(),
            'categories' => ReportCategory::cases(),
        ]);
    }

    public function show(Report $report): View
    {
        $report->load(['reporter', 'reviewer']);

        return view('manage.reports.show', ['report' => $report]);
    }

    public function resolve(Report $report): RedirectResponse
    {
        $this->transition($report, ReportStatus::REVIEWED);

        return back()->with('status', 'تم تعليم البلاغ كمراجَع.');
    }

    public function dismiss(Report $report): RedirectResponse
    {
        $this->transition($report, ReportStatus::DISMISSED);

        return back()->with('status', 'تم رفض البلاغ.');
    }

    public function action(Request $request, Report $report): RedirectResponse
    {
        $data = $request->validate([
            'admin_notes' => ['required', 'string', 'max:1000'],
        ]);

        $this->transition($report, ReportStatus::ACTIONED, $data['admin_notes']);

        return back()->with('status', 'تم اتخاذ إجراء على البلاغ.');
    }

    private function transition(Report $report, ReportStatus $status, ?string $notes = null): void
    {
        $report->forceFill([
            'status' => $status,
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
            'admin_notes' => $notes ?? $report->admin_notes,
        ])->save();
    }
}
