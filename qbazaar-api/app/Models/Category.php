<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $parent_id
 * @property string $slug
 * @property array{ar: string, en: string} $name
 * @property array{ar: string, en: string}|null $description
 * @property string|null $icon
 * @property int $order
 * @property bool $is_active
 * @property array<int, array<string, mixed>>|null $custom_fields
 * @property array<int, array<string, mixed>>|null $custom_filters
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Category|null $parent
 * @property Collection<int, Category> $children
 */
class Category extends Model
{
    use HasUlids;

    protected $table = 'categories';

    /** @var string */
    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'parent_id',
        'slug',
        'name',
        'description',
        'icon',
        'order',
        'is_active',
        'custom_fields',
        'custom_filters',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'is_active' => 'boolean',
            'order' => 'integer',
            'custom_fields' => 'array',
            'custom_filters' => 'array',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Category, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('order');
    }

    /**
     * Restrict a query to active categories only.
     *
     * @param Builder<Category> $query
     * @return Builder<Category>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Categories without children — the level ads are filed under.
     *
     * @param Builder<Category> $query
     * @return Builder<Category>
     */
    public function scopeLeaf(Builder $query): Builder
    {
        return $query->whereDoesntHave('children');
    }

    /**
     * Locale-aware accessor for the name. Falls back to English when the
     * requested locale isn't populated — every category MUST have at least
     * an English label.
     */
    public function getLocalizedName(string $locale): string
    {
        return $this->name[$locale] ?? $this->name['en'] ?? $this->slug;
    }
}
