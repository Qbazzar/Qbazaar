<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage\Catalog;

use App\Services\Catalog\Taxonomy\TaxonomyKind;
use Illuminate\Http\Request;

/**
 * Categories and locations share their bulk and status requests; the route
 * name says which tree a request acts on.
 */
final class TaxonomyRoute
{
    public static function kind(Request $request): TaxonomyKind
    {
        $name = (string) $request->route()?->getName();

        return str_starts_with($name, 'admin.' . TaxonomyKind::LOCATION->routeName() . '.')
            ? TaxonomyKind::LOCATION
            : TaxonomyKind::CATEGORY;
    }
}
