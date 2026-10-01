<?php

declare(strict_types=1);

namespace App\Jobs\Media;

use Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob as BasePerformConversionsJob;

/**
 * MediaLibrary's conversion job with explicit retry limits. Conversions
 * overwrite the same files, so a retry after a failure or timeout is safe.
 */
class PerformConversionsJob extends BasePerformConversionsJob
{
    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public int $timeout = 300;
}
