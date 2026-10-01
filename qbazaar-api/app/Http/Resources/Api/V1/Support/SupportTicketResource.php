<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Support;

use App\Models\SupportTicket;
use Illuminate\Http\Request;

/**
 * A ticket with its whole reply thread, oldest reply first. Expects
 * `replies.author` to be loaded.
 *
 * @mixin SupportTicket
 */
class SupportTicketResource extends SupportTicketSummaryResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'body' => $this->body,
            'email' => $this->email,
            'replies' => SupportReplyResource::collection(
                $this->resource->replies->sortBy('created_at')->values(),
            )->resolve($request),
        ];
    }
}
