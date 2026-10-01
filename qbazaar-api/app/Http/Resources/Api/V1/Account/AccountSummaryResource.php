<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Account;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Counters for the account dashboard, assembled by
 * App\Actions\Account\GetAccountSummaryAction.
 *
 * @property array{
 *   my_ads: int,
 *   drafts: int,
 *   ads_by_status: array<string, int>,
 *   conversations: int,
 *   unread_messages: int,
 *   unread_notifications: int,
 *   favorites: int,
 *   saved_searches: int
 * } $resource
 */
class AccountSummaryResource extends JsonResource
{
    /**
     * @return array<string, int|array<string, int>>
     */
    public function toArray(Request $request): array
    {
        $data = $this->resource;

        return [
            'my_ads' => $data['my_ads'],
            'drafts' => $data['drafts'],
            'ads_by_status' => $data['ads_by_status'],
            'conversations' => $data['conversations'],
            'unread_messages' => $data['unread_messages'],
            'unread_notifications' => $data['unread_notifications'],
            'favorites' => $data['favorites'],
            'saved_searches' => $data['saved_searches'],
        ];
    }
}
