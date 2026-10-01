<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Per-user saved search snapshot.
 *
 * `query_params` is replayed verbatim against `/search`. The structured
 * filters in it are mirrored into indexed columns (see SavedSearchCriteria)
 * so new-ad alerts pre-filter in SQL.
 *
 * @property string $id
 * @property string $user_id
 * @property string $name
 * @property array<string, mixed> $query_params
 * @property bool $alerts_enabled
 * @property string|null $category_id
 * @property string|null $location_id
 * @property string|null $condition
 * @property string|null $price_type
 * @property string|null $price_min
 * @property string|null $price_max
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property User $user
 */
class SavedSearch extends Model
{
    use HasUlids;

    /**
     * Stored when a filter names a category or location that does not exist,
     * so the search matches nothing instead of everything.
     */
    public const MATCHES_NOTHING = '00000000000000000000000000';

    protected $table = 'saved_searches';

    /** @var string */
    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'query_params',
        'alerts_enabled',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'alerts_enabled' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'query_params' => 'array',
            'alerts_enabled' => 'boolean',
            'price_min' => 'decimal:2',
            'price_max' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
