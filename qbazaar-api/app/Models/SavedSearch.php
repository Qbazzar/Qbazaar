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
 * @property string $id
 * @property string $user_id
 * @property string $name
 * @property array<string, mixed> $query_params
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property User $user
 */
class SavedSearch extends Model
{
    use HasUlids;

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
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'query_params' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
