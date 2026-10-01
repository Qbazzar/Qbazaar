<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $user_id
 * @property string|null $business_name
 * @property string|null $about
 * @property string|null $legal_name
 * @property string|null $commercial_registration_number
 * @property string|null $contact_phone
 * @property string|null $contact_email
 * @property string|null $website
 * @property string|null $address
 * @property list<array{day: string, closed: bool, open?: string|null, close?: string|null}>|null $opening_hours
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property User $user
 */
class BusinessProfile extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'business_name',
        'about',
        'legal_name',
        'commercial_registration_number',
        'contact_phone',
        'contact_email',
        'website',
        'address',
        'opening_hours',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opening_hours' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
