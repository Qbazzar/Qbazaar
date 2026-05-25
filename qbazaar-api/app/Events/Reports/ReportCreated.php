<?php

declare(strict_types=1);

namespace App\Events\Reports;

use App\Models\Report;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a user-submitted report has been persisted. Currently has no
 * production listeners — Sprint 11 will hook the Filament moderation
 * notifier here so admins receive an in-app ping the moment a new abuse
 * report lands. The event is dispatched today so tests can lock the
 * contract and the listener can be added without touching the action.
 */
class ReportCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Report $report) {}
}
