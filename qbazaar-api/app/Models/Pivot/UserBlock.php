<?php

declare(strict_types=1);

namespace App\Models\Pivot;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * Custom Pivot model so the `created_at` column on `user_blocks` auto-casts
 * to a Carbon instance — letting resources call ->toIso8601String() without
 * defensive parsing.
 *
 * @property Carbon|null $created_at
 */
class UserBlock extends Pivot
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
