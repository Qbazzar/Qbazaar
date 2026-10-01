<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserAddressFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string|null $label
 * @property string $full_name
 * @property string|null $phone
 * @property string $street
 * @property string $house_number
 * @property string|null $supplement
 * @property string $city
 * @property string|null $postal_code
 * @property string|null $location_id
 * @property bool $is_default
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class UserAddress extends Model
{
    /** @use HasFactory<UserAddressFactory> */
    use HasFactory, HasUlids;

    /**
     * `user_id` and `is_default` are owned by SavedAddressService.
     *
     * @var list<string>
     */
    protected $fillable = [
        'label',
        'full_name',
        'phone',
        'street',
        'house_number',
        'supplement',
        'city',
        'postal_code',
        'location_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param Builder<UserAddress> $query
     * @return Builder<UserAddress>
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }
}
