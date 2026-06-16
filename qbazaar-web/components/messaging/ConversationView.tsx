'use client';

/**
 * Right pane of the inbox — the open conversation.
 *
 * Subscribes to the per-conversation Echo channel so new messages from the
 * other participant land instantly in the infinite cache without polling.
 * On mount we call markRead to clear the unread badge once the user has
 * actually seen the thread.
 */
import { useCallback, useEffect } from 'react';
import Image from 'next/image';
import Link from 'next/link';
import { ArrowLeftIcon, ImageIcon, Loader2Icon } from 'lucide-react';
import { useQueryClient } from '@tanstack/react-query';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { PriceTag } from '@/components/ads/PriceTag';
import { MessageList } from './MessageList';
import { ChatInput } from './ChatInput';
import {
  appendIncomingMessageToCache,
  useConversationQuery,
  useMarkReadMutation,
  messagingKeys,
} from '@/lib/queries/messaging';
import { offersKeys } from '@/lib/queries/offers';
import {
  useConversationChannel,
  type OfferEvent,
} from '@/lib/echo/useConversationChannel';
import { useTypingIndicator } from '@/lib/echo/useTypingIndicator';
import { useAuth } from '@/hooks/useAuth';
import { t } from '@/lib/i18n/messages';
import type { Message, Offer } from '@/lib/api/types';

interface Props {
  conversationId: string;
  onBack: () => void;
}

export function ConversationView({ conversationId, onBack }: Props) {
  const qc = useQueryClient();
  const { user } = useAuth();
  const { data: conversation, isLoading, isError, error } = useConversationQuery(
    conversationId,
  );
  const markRead = useMarkReadMutation();

  // Real-time push: incoming message → drop into the infinite cache.
  const onIncoming = useCallback(
    (message: Message) => {
      appendIncomingMessageToCache(qc, message);
      qc.invalidateQueries({ queryKey: messagingKeys.lists() });
    },
    [qc],
  );

  // Real-time push: offer lifecycle event → invalidate offers + messages so
  // the OfferBubble re-renders with the latest status and any new offer
  // message lands in the timeline.
  const onOfferEvent = useCallback(
    (_event: OfferEvent, offer: Offer) => {
      qc.invalidateQueries({
        queryKey: offersKeys.byConversation(offer.conversation_id),
      });
      qc.invalidateQueries({
        queryKey: messagingKeys.messages(offer.conversation_id),
      });
      qc.invalidateQueries({ queryKey: messagingKeys.lists() });
    },
    [qc],
  );

  useConversationChannel(conversationId, {
    onMessage: onIncoming,
    onOfferEvent,
  });

  // Peer-to-peer typing presence over the same channel (whispers — no
  // backend round-trip). `notifyTyping` is throttled inside the hook.
  const { isPeerTyping, notifyTyping } = useTypingIndicator(conversationId);

  // Fire-and-forget mark-read whenever the open conversation has unread
  // messages. Re-runs when the id changes (switching threads).
  useEffect(() => {
    if (!conversation) return;
    if (conversation.unread_count > 0) {
      markRead.mutate(conversationId);
    }
    // We intentionally exclude `markRead` from deps so a re-render of the
    // mutation object doesn't refire the call.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [conversationId, conversation?.unread_count]);

  if (isLoading) {
    return (
      <div className="flex h-full items-center justify-center" role="status">
        <Loader2Icon
          className="text-muted-foreground size-5 animate-spin"
          aria-hidden
        />
      </div>
    );
  }

  if (isError || !conversation) {
    const code = (error as { code?: string } | null)?.code;
    const isNotFound = code === 'CONVERSATION_NOT_FOUND';
    return (
      <div className="text-ink-700 flex h-full flex-col items-center justify-center gap-3 p-8 text-center">
        <p>
          {isNotFound
            ? t(
                'messaging.errors.conversation_not_found',
                'لم نعثر على المحادثة',
              )
            : t('common.error', 'حدث خطأ، حاول مرة أخرى')}
        </p>
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={onBack}
          className="rounded-full"
        >
          {t('messaging.back', 'العودة')}
        </Button>
      </div>
    );
  }

  const adImage =
    conversation.ad.primary_image?.sizes.thumbnail ??
    conversation.ad.primary_image?.url ??
    null;

  // Buyer vs seller view drives the offer affordances: only buyers see the
  // "Make offer" button, only sellers see accept/reject on a pending offer.
  const viewerRole: 'buyer' | 'seller' =
    user?.id === conversation.seller_id ? 'seller' : 'buyer';

  return (
    <div className="flex h-full flex-col">
      {/* Top bar */}
      <header className="border-ink-200 bg-card flex items-center gap-3 border-b px-3 py-2.5">
        <Button
          type="button"
          variant="ghost"
          size="icon"
          onClick={onBack}
          className="lg:hidden"
          aria-label={t('messaging.back', 'العودة')}
        >
          <ArrowLeftIcon className="size-5 rtl:rotate-180" aria-hidden />
        </Button>

        <Link
          href={`/ads/${conversation.ad.id}`}
          className="flex min-w-0 flex-1 items-center gap-3"
        >
          <div className="bg-cream-200 relative size-10 shrink-0 overflow-hidden rounded-lg">
            {adImage ? (
              <Image
                src={adImage}
                alt={conversation.ad.title}
                fill
                sizes="40px"
                className="object-cover"
              />
            ) : (
              <div className="text-ink-500 flex size-full items-center justify-center">
                <ImageIcon className="size-4" aria-hidden />
              </div>
            )}
          </div>
          <div className="min-w-0 flex-1">
            <p className="text-ink-900 truncate text-sm font-bold">
              {conversation.ad.title}
            </p>
            <div className="text-ink-500 truncate text-[11px]">
              <PriceTag
                price={conversation.ad.price}
                priceType={conversation.ad.price_type}
                size="sm"
              />
            </div>
          </div>
        </Link>

        <div className="flex items-center gap-2">
          <span className="text-ink-700 hidden text-xs sm:inline">
            {conversation.other_participant.full_name}
          </span>
          <Avatar className="size-9">
            {conversation.other_participant.avatar_thumb_url ? (
              <Image
                src={conversation.other_participant.avatar_thumb_url}
                alt={conversation.other_participant.full_name}
                width={36}
                height={36}
                className="size-full rounded-full object-cover"
              />
            ) : (
              <AvatarFallback>
                {conversation.other_participant.full_name.charAt(0) || '?'}
              </AvatarFallback>
            )}
          </Avatar>
        </div>
      </header>

      <MessageList conversationId={conversationId} />

      {/* Height is always reserved (min-h-5) so the indicator appearing or
          decaying never shifts the message list / input. */}
      <p
        className="text-ink-500 min-h-5 shrink-0 px-4 text-[11px]"
        aria-live="polite"
      >
        {isPeerTyping ? t('messaging.typing', 'يكتب الآن…') : null}
      </p>

      <ChatInput
        conversationId={conversationId}
        viewerRole={viewerRole}
        onTyping={notifyTyping}
      />
    </div>
  );
}
