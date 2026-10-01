<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Sanctum stamps last_used_at on every authenticated request, which is one
 * UPDATE per poll from every open tab. The sessions screen only needs it to
 * the minute, so a stamp newer than the configured interval is not rewritten.
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    public function save(array $options = []): bool
    {
        if ($this->isRecentLastUsedStampOnly()) {
            $this->last_used_at = $this->getOriginal('last_used_at');

            return true;
        }

        return parent::save($options);
    }

    private function isRecentLastUsedStampOnly(): bool
    {
        if (! $this->exists || array_keys($this->getDirty()) !== ['last_used_at']) {
            return false;
        }

        $previous = $this->getOriginal('last_used_at');
        $interval = (int) config('qbazaar.auth.token_last_used_write_minutes');

        return $previous instanceof CarbonInterface && $previous->gt(Carbon::now()->subMinutes($interval));
    }
}
