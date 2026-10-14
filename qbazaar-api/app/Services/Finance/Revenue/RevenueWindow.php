<?php

declare(strict_types=1);

namespace App\Services\Finance\Revenue;

use App\Support\Sql\LocalBucket;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The half-open range [start, end) a revenue report covers, the equally long
 * one before it for the trend arrows, and the bucket size its chart uses.
 * Bounds are kept in Qatar time; queries take the UTC versions.
 */
final readonly class RevenueWindow
{
    private const int DAY_BUCKETS_UP_TO = 92;

    private const int WEEK_BUCKETS_UP_TO = 400;

    public function __construct(
        public RevenueRange $range,
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public CarbonImmutable $previousStart,
        public CarbonImmutable $previousEnd,
    ) {}

    public function startUtc(): CarbonImmutable
    {
        return $this->start->utc();
    }

    public function endUtc(): CarbonImmutable
    {
        return $this->end->utc();
    }

    public function previousStartUtc(): CarbonImmutable
    {
        return $this->previousStart->utc();
    }

    public function previousEndUtc(): CarbonImmutable
    {
        return $this->previousEnd->utc();
    }

    public function days(): int
    {
        return max(1, (int) ceil(($this->end->getTimestamp() - $this->start->getTimestamp()) / 86_400));
    }

    public function bucket(): string
    {
        return match (true) {
            $this->days() <= self::DAY_BUCKETS_UP_TO => LocalBucket::DAY,
            $this->days() <= self::WEEK_BUCKETS_UP_TO => LocalBucket::WEEK,
            default => LocalBucket::MONTH,
        };
    }

    /**
     * First day of every bucket the range touches, so a chart has a point
     * (zero when nothing happened) for each of them.
     *
     * @return list<string> local dates, Y-m-d
     */
    public function bucketStarts(): array
    {
        $bucket = $this->bucket();
        $cursor = match ($bucket) {
            LocalBucket::WEEK => $this->start->startOfWeek(CarbonInterface::MONDAY),
            LocalBucket::MONTH => $this->start->startOfMonth(),
            default => $this->start->startOfDay(),
        };

        $starts = [];
        while ($cursor->lessThan($this->end)) {
            $starts[] = $cursor->toDateString();
            $cursor = match ($bucket) {
                LocalBucket::WEEK => $cursor->addWeek(),
                LocalBucket::MONTH => $cursor->addMonthNoOverflow(),
                default => $cursor->addDay(),
            };
        }

        return $starts;
    }

    /**
     * Same range, same cache entry: rolling ranges end now, so the key holds
     * the local dates and a new day starts a new entry.
     */
    public function cacheKey(): string
    {
        return implode(':', [$this->range->value, $this->start->toDateString(), $this->end->subSecond()->toDateString()]);
    }

    public function label(): string
    {
        $last = $this->end->subSecond();
        $format = fn (CarbonImmutable $date): string => $date->translatedFormat('j M Y');

        return $this->start->isSameDay($last) ? $format($this->start) : $format($this->start) . ' – ' . $format($last);
    }
}
