<?php

declare(strict_types=1);

namespace App\Services\Catalog\Taxonomy;

use App\Models\Category;
use App\Models\Location;
use App\Services\Catalog\CachedHierarchy;
use App\Services\Catalog\CategoryHierarchy;
use App\Services\Catalog\LocationHierarchy;
use Illuminate\Database\Eloquent\Model;

/**
 * The two self-referencing trees an admin curates. Both are referenced by
 * ads and saved searches; only locations are referenced by user addresses.
 */
enum TaxonomyKind: string
{
    case CATEGORY = 'category';
    case LOCATION = 'location';

    public static function of(Model $node): self
    {
        return $node instanceof Location ? self::LOCATION : self::CATEGORY;
    }

    /**
     * @return class-string<Category|Location>
     */
    public function model(): string
    {
        return match ($this) {
            self::CATEGORY => Category::class,
            self::LOCATION => Location::class,
        };
    }

    public function table(): string
    {
        return match ($this) {
            self::CATEGORY => 'categories',
            self::LOCATION => 'locations',
        };
    }

    /** The column ads, saved searches and addresses point at it with. */
    public function foreignKey(): string
    {
        return match ($this) {
            self::CATEGORY => 'category_id',
            self::LOCATION => 'location_id',
        };
    }

    public function tracksAddresses(): bool
    {
        return $this === self::LOCATION;
    }

    /** The resource segment of its admin routes. */
    public function routeName(): string
    {
        return match ($this) {
            self::CATEGORY => 'categories',
            self::LOCATION => 'locations',
        };
    }

    /** The permission that guards its admin screens and actions. */
    public function permission(): string
    {
        return $this->routeName() . '.manage';
    }

    /** The cached id → parent map of the tree. */
    public function hierarchy(): CachedHierarchy
    {
        return match ($this) {
            self::CATEGORY => app(CategoryHierarchy::class),
            self::LOCATION => app(LocationHierarchy::class),
        };
    }
}
