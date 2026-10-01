<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Support;

use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A ticket row in the owner's inbox.
 *
 * @mixin SupportTicket
 */
class SupportTicketSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'category' => $this->category->value,
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'last_replied_at' => $this->last_replied_at?->toIso8601String(),
            'replies_count' => $this->repliesCount(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    private function repliesCount(): int
    {
        $ticket = $this->resource;

        if ($ticket->replies_count !== null) {
            return (int) $ticket->replies_count;
        }

        return $ticket->relationLoaded('replies') ? $ticket->replies->count() : $ticket->replies()->count();
    }
}
