<?php

declare(strict_types=1);

namespace App\Events\Reports;

use App\Models\Report;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a report has been persisted, by a user or by the chat screen.
 * NotifyModeratorsOfReport puts it in the bell of the staff who handle reports.
 */
class ReportCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Report $report) {}
}
