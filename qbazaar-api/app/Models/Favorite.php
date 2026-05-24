<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FavoriteFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Favorite — a user's "saved" ad. One row per (user, ad) pair; toggling
 * off deletes the row rather than flipping a flag, so the schema has no
 * `updated_at` column.
 *
 * @property string $id
 * @property string $user_id
 * @property string $ad_id
 * @property Carbon|null $created_at
 * @property User $user
 * @property Ad $ad
 */
class Favorite extends Model
{
    /** @use HasFactory<FavoriteFactory> */
    use HasFactory, HasUlids;

    protected $table = 'favorites';

    /** @var string */
    protected $keyType = 'string';

    /**
     * Only `created_at` is managed; the table has no `updated_at` column
     * (favourites are immutable — see migration).
     *
     * @var string|null
     */
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'ad_id',
        'created_at',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Ad, $this> */
    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }
}
