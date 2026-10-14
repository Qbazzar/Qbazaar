<?php

declare(strict_types=1);

namespace App\Services\Admin\Ads;

use App\Data\Admin\AdDeal\ConversationRow;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\Offer;
use App\Services\Messaging\ChatMessageRenderer;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * The newest conversations and offers on an ad. Conversations come from
 * conversations_ad_last_msg_idx and offers from offers_ad_created_idx; their
 * inbox state is one query for all the listed rows, not one per row.
 */
final class AdChatPanel
{
    public function __construct(private readonly ChatMessageRenderer $renderer) {}

    /**
     * @return Collection<int, ConversationRow>
     */
    public function conversations(Ad $ad, int $limit = AdTransactionSummary::LIST_LIMIT): Collection
    {
        $conversations = Conversation::query()
            ->where('ad_id', $ad->id)
            ->with(['buyer', 'seller', 'buyer.avatarMedia', 'seller.avatarMedia'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $inbox = $this->inboxState($conversations);

        return $conversations->map(fn (Conversation $conversation): ConversationRow => $this->rowOf($conversation, $inbox));
    }

    /**
     * @return EloquentCollection<int, Offer>
     */
    public function offers(Ad $ad, int $limit): EloquentCollection
    {
        return Offer::query()
            ->where('ad_id', $ad->id)
            ->with(['buyer', 'buyer.avatarMedia'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function offerCount(Ad $ad): int
    {
        return Offer::query()->where('ad_id', $ad->id)->count();
    }

    /**
     * @param EloquentCollection<int, Conversation> $conversations
     * @return Collection<array-key, Collection<int, stdClass>> participant rows by conversation id
     */
    private function inboxState(EloquentCollection $conversations): Collection
    {
        if ($conversations->isEmpty()) {
            return collect();
        }

        return DB::table('conversation_participants')
            ->whereIn('conversation_id', $conversations->modelKeys())
            ->get(['conversation_id', 'user_id', 'hidden_at', 'unread_count'])
            ->groupBy('conversation_id');
    }

    /**
     * @param Collection<array-key, Collection<int, stdClass>> $inbox
     */
    private function rowOf(Conversation $conversation, Collection $inbox): ConversationRow
    {
        $sides = $inbox->get($conversation->id, collect());
        $hidden = fn (string $userId): bool => $sides->contains(fn (stdClass $side): bool => $side->user_id === $userId && $side->hidden_at !== null);

        return new ConversationRow(
            conversation: $conversation,
            excerpt: $this->renderer->preview($conversation, app()->getLocale()),
            unread: (int) $sides->sum('unread_count'),
            hiddenByBuyer: $hidden($conversation->buyer_id),
            hiddenBySeller: $hidden($conversation->seller_id),
        );
    }
}
