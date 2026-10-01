<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Support;

use App\Models\SupportReply;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SupportReply
 */
class SupportReplyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'author' => [
                'id' => $this->author->id,
                'name' => $this->author->full_name,
                'is_staff' => $this->is_staff,
            ],
            'body' => $this->body,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
