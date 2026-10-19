<?php

declare(strict_types=1);

namespace App\Services\Admin\Dashboard\Collectors;

use App\Models\Conversation;
use App\Models\Favorite;
use App\Models\Follow;
use App\Models\Message;
use App\Models\Offer;
use App\Models\PurchaseRequest;
use App\Models\SavedSearch;
use App\Services\Admin\Dashboard\DashboardAccess;
use App\Services\Admin\Dashboard\DashboardWindow;

/**
 * Rows created per window in the chat, offer, bookmark and follow tables. Each
 * is one ranged count on its `created_at` index (see the dashboard index
 * migrations).
 */
final class EngagementStats
{
    /** Tile key => [model, section that may see it]. */
    private const array SOURCES = [
        'conversations_new' => [Conversation::class, DashboardAccess::CONVERSATIONS],
        'messages_new' => [Message::class, DashboardAccess::CONVERSATIONS],
        'offers_new' => [Offer::class, DashboardAccess::OFFERS],
        'purchase_requests_new' => [PurchaseRequest::class, DashboardAccess::OFFERS],
        'favorites_new' => [Favorite::class, DashboardAccess::CONTENT],
        'saved_searches_new' => [SavedSearch::class, DashboardAccess::USERS],
        'follows_new' => [Follow::class, DashboardAccess::USERS],
    ];

    /**
     * @param list<string> $sections
     * @return list<array<string, mixed>>
     */
    public function tiles(DashboardWindow $window, array $sections): array
    {
        $tiles = [];

        foreach (self::SOURCES as $key => [$model, $section]) {
            if (in_array($section, $sections, true)) {
                $tiles[] = ['key' => $key, 'kind' => 'count'] + $window->trend($model::query());
            }
        }

        return $tiles;
    }
}
