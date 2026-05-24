<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RecentViewFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * RecentView — append-only log of which user (or anonymous session)
 * viewed which ad and when. We keep multiple rows per (viewer, ad) so
 * the inline cap-50 cleanup can evict by `viewed_at` order; spam is
 * prevented at the action layer (hourly Redis lock), not in the schema.
 *
 * @property string $id
 * @property string|null $user_id
 * @property string|null $session_id
 * @property string $ad_id
 * @property Carbon $viewed_at
 * @property User|null $user
 * @property Ad $ad
 */
class RecentView extends Model
{
    /** @use HasFactory<RecentViewFactory> */
    use HasFactory, HasUlids;

    protected $table = 'recently_viewed';

    /** @var string */
    protected $keyType = 'string';

    /**
     * Append-only log — no managed timestamps. `viewed_at` is the only
     * temporal column and it's set explicitly by the action.
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'session_id',
        'ad_id',
        'viewed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
        ];
    }

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
