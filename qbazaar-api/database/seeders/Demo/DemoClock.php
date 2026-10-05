<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use Closure;
use Illuminate\Support\Carbon;

/**
 * Runs the real actions and services at a moment in the past, so the
 * timestamps they write (published_at, offer expiry, ledger dates,
 * notifications) tell a believable story instead of all saying "just now".
 */
final class DemoClock
{
    private readonly Carbon $now;

    public function __construct()
    {
        $this->now = Carbon::now();
    }

    public function now(): Carbon
    {
        return $this->now->copy();
    }

    public function daysAgo(int $days, int $extraMinutes = 0): Carbon
    {
        return $this->now->copy()->subDays($days)->subMinutes($extraMinutes);
    }

    /**
     * @template T
     *
     * @param Closure(): T $callback
     * @return T
     */
    public function at(Carbon $moment, Closure $callback): mixed
    {
        $previous = Carbon::hasTestNow() ? Carbon::getTestNow() : null;
        Carbon::setTestNow($moment->min($this->now));

        try {
            return $callback();
        } finally {
            Carbon::setTestNow($previous);
        }
    }
}
