<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Reference;

use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A place by name only: no coordinates and no subtree. Used where a place
 * describes a person (where a seller sells from), which stays at city or
 * area level.
 *
 * @mixin Location
 */
class LocationSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>|null
     */
    public static function orNull(?Location $location, Request $request): ?array
    {
        return $location !== null ? (new self($location))->toArray($request) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'slug' => $this->slug,
            'name' => $this->name,
            'type' => $this->type->value,
        ];
    }
}
