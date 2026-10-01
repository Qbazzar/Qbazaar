<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Reference;

use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Recursive location node — same shape used for cities and their districts.
 * `children` is rendered only when loaded (see TreeAssembler), so an ad's
 * location never walks its subtree lazily.
 *
 * @mixin Location
 */
class LocationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $children = $this->resource->relationLoaded('children')
            ? $this->children->map(fn (Location $child): array => (new self($child))->toArray($request))->all()
            : [];

        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'slug' => $this->slug,
            'name' => $this->name,
            'type' => $this->type->value,
            'lat' => $this->lat !== null ? (float) $this->lat : null,
            'lng' => $this->lng !== null ? (float) $this->lng : null,
            'children' => $children,
        ];
    }
}
