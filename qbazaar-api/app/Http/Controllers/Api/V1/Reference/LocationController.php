<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reference;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Reference\LocationResource;
use App\Models\Location;
use App\Services\Catalog\CatalogCache;
use App\Services\Catalog\TreeAssembler;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Read-only location endpoints.
 *
 * Qatar's taxonomy is static (added by seeders only), so a 24-hour TTL is
 * appropriate — any change to it goes through a deploy and we can invalidate
 * by key explicitly.
 *
 * @group Reference
 */
class LocationController extends Controller
{
    private const QATAR_TTL = 86400; // 24 hours

    /**
     * GET /api/v1/locations/qatar — full Qatar location tree.
     *
     * @unauthenticated
     */
    public function qatar(Request $request, TreeAssembler $assembler): JsonResponse
    {
        /** @var Collection<int, Location> $roots */
        $roots = Cache::remember(
            CatalogCache::LOCATION_TREE_KEY,
            self::QATAR_TTL,
            fn () => $assembler->nest(Location::query()->orderBy('order')->get()),
        );

        // Map to plain arrays — see CategoryController::tree for the rationale.
        $data = $roots->map(
            fn (Location $node): array => (new LocationResource($node))->toArray($request),
        )->all();

        return response()->json($data);
    }
}
