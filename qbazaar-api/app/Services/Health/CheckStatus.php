<?php

declare(strict_types=1);

namespace App\Services\Health;

/**
 * Outcome of one readiness check. `DISABLED` means the dependency is not used
 * by this deployment (a sync queue, a non-Meilisearch search driver), so it
 * counts as healthy.
 */
enum CheckStatus: string
{
    case OK = 'ok';
    case FAIL = 'fail';
    case STALE = 'stale';
    case UNKNOWN = 'unknown';
    case DISABLED = 'disabled';

    public function isHealthy(): bool
    {
        return $this === self::OK || $this === self::DISABLED;
    }
}
