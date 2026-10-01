<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $device_hash
 * @property string|null $label
 * @property string|null $last_ip
 * @property Carbon $last_used_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class TrustedDevice extends Model
{
    use HasUlids;
    use MassPrunable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'device_hash',
        'label',
        'last_ip',
        'last_used_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * A device unused for this long goes back through the new-device check.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where(
            'last_used_at',
            '<',
            Carbon::now()->subDays((int) config('qbazaar.auth.new_device_check.trusted_device_days')),
        );
    }
}
