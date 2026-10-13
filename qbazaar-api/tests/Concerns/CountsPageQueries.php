<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Query budgets for admin pages: a warm-up request first (permission cache,
 * settings), then the queries of a second, identical request.
 */
trait CountsPageQueries
{
    protected function steadyQueryCount(string $uri): int
    {
        $this->get($uri)->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($uri)->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    }
}
