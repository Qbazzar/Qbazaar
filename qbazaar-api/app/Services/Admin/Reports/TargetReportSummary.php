<?php

declare(strict_types=1);

namespace App\Services\Admin\Reports;

use App\Models\Report;
use Illuminate\Database\Eloquent\Collection;

/**
 * The newest reports against one target and how many there are in all.
 */
final readonly class TargetReportSummary
{
    /**
     * @param Collection<int, Report> $latest
     */
    public function __construct(
        public Collection $latest,
        public int $total,
        public int $open,
    ) {}

    public function hasMore(): bool
    {
        return $this->total > $this->latest->count();
    }
}
