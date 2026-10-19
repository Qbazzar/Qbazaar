<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A staff member's TOTP credential for the admin panel.
 *
 * @property string $user_id
 * @property string $secret
 * @property list<string>|null $recovery_codes SHA-256 hashes
 * @property Carbon|null $confirmed_at
 * @property int|null $last_used_step
 * @property int $device_version
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class StaffTwoFactor extends Model
{
    protected $table = 'staff_two_factor';

    protected $primaryKey = 'user_id';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * Every column is written by StaffTwoFactorService through forceFill.
     *
     * @var list<string>
     */
    protected $fillable = [];

    /**
     * @var list<string>
     */
    protected $hidden = ['secret', 'recovery_codes'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'recovery_codes' => 'array',
            'confirmed_at' => 'datetime',
            'last_used_step' => 'integer',
            'device_version' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    public function remainingRecoveryCodes(): int
    {
        return count($this->recovery_codes ?? []);
    }
}
