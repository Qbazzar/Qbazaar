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
import Link from 'next/link';
import { ArrowLeft } from 'lucide-react';
import { useQueryClient } from '@tanstack/react-query';

import { Button } from '@/components/design-system/Button';
import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { AdPhoto } from '@/components/account/AdPhoto';
import { PanelState } from '@/components/account/PanelState';
import { formatAdPrice } from '@/components/account/format';
import { cn } from '@/lib/utils';
import { MessageList } from './MessageList';
import { ChatInput } from './ChatInput';
import { ChatSettingsMenu } from './ChatSettingsMenu';
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
  // the offer card re-renders with the latest status and any new offer
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
    return <PanelState loading className="flex h-full items-center" />;
  }

  if (isError || !conversation) {
    const code = (error as { code?: string } | null)?.code;
    // MSG_004: no such conversation; MSG_005: the viewer is not part of it.
    const isNotFound = code === 'MSG_004' || code === 'MSG_005';
    return (
      <div className="flex h-full flex-col items-center justify-center gap-4 p-8 text-center">
        <p role="alert" className="text-qb-body text-qb-ink-body">
          {isNotFound
            ? t('messaging.errors.conversation_not_found', 'لم نعثر على المحادثة')
            : t('common.error', 'حدث خطأ، حاول مرة أخرى')}
        </p>
        <Button variant="outline" size="sm" onClick={onBack}>
          {t('messaging.back', 'العودة')}
        </Button>
      </div>
    );
  }

  // Buyer vs seller view drives the offer affordances: only buyers see the
  // "Make offer" button, only sellers see accept/reject on a pending offer.
  const viewerRole: 'buyer' | 'seller' =
    user?.id === conversation.seller_id ? 'seller' : 'buyer';

  return (
    <div className="flex h-full min-h-0 flex-col">
      <header className="flex items-center gap-3 border-b border-qb-line px-4 py-[18px] qb-desktop:px-7 qb-desktop:py-[22px]">
        <Button
          variant="ghost"
          size="icon"
          onClick={onBack}
          className="-ms-2 qb-desktop:hidden"
          aria-label={t('messaging.back', 'العودة')}
        >
          <Icon icon={ArrowLeft} size="lg" flipInRtl />
        </Button>

        <Link
          href={`/ads/${conversation.ad.id}`}
          className={cn('flex min-w-0 flex-1 items-center gap-3 rounded-qb-md', focusRing)}
        >
          <AdPhoto
            image={conversation.ad.primary_image}
            sizes="44px"
            compact
            className="size-11 rounded-qb-lg qb-desktop:hidden"
          />
          <span className="min-w-0">
            <span className="block truncate text-qb-body-lg font-semibold text-qb-ink qb-desktop:text-qb-h3">
              <bdi>{conversation.ad.title}</bdi>
            </span>
            <span className="mt-1 block font-semibold text-qb-brand">{formatAdPrice(conversation.ad)}</span>
          </span>
        </Link>

        <ChatSettingsMenu peer={conversation.other_participant} />
      </header>

      <MessageList conversationId={conversationId} />

      {/* Height is always reserved (min-h-5) so the indicator appearing or
          decaying never shifts the message list / input. */}
      <p className="min-h-5 shrink-0 px-6 text-qb-micro text-qb-ink-subtle" aria-live="polite">
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
