<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Search;

use App\Models\SavedSearch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SavedSearch
 */
class SavedSearchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'query_params' => $this->query_params,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
