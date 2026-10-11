<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FavoriteCategoryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A category a user pinned with "Add to Favorite" on its page. One row per
 * (user, category) pair; removing it deletes the row.
 *
 * @property string $id
 * @property string $user_id
 * @property string $category_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property User $user
 * @property Category $category
 */
class FavoriteCategory extends Model
{
    /** @use HasFactory<FavoriteCategoryFactory> */
    use HasFactory, HasUlids;

    protected $table = 'user_favorite_categories';

    /** @var string */
    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'category_id',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
