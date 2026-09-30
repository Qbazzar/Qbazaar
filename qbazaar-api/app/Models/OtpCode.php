<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OtpPurpose;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $phone
 * @property OtpPurpose $purpose
 * @property string $code_hash
 * @property int $attempts
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class OtpCode extends Model
{
    use HasUlids;

    protected $table = 'otp_codes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'phone',
        'purpose',
        'code_hash',
        'attempts',
        'expires_at',
        'used_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => OtpPurpose::class,
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isActive(): bool
    {
        return ! $this->isUsed() && ! $this->isExpired();
    }
}
