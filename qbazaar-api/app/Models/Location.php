<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LocationType;
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
 * @property LocationType $type
 * @property string|null $lat
 * @property string|null $lng
 * @property int $order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Location|null $parent
 * @property Collection<int, Location> $children
 */
class Location extends Model
{
    use HasUlids;

    protected $table = 'locations';

    /** @var string */
    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'parent_id',
        'slug',
        'name',
        'type',
        'lat',
        'lng',
        'order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => 'array',
            'type' => LocationType::class,
            'order' => 'integer',
        ];
    }

    /** @return BelongsTo<Location, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Location, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('order');
    }

    /** @return HasMany<Ad, $this> */
    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }

    public function getLocalizedName(string $locale): string
    {
        return $this->name[$locale] ?? $this->name['en'] ?? $this->slug;
    }
}
