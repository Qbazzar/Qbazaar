'use client';

/**
 * Scrollable message timeline.
 *
 * - The infinite query returns pages newest-first; we flatten + reverse so
 *   the visual order is oldest-at-top.
 * - Day separators are inserted whenever two consecutive messages fall on
 *   different calendar days.
 * - Avatars only render on the first message in a streak from the same
 *   sender so the column stays tidy.
 * - The container auto-scrolls to the bottom on first paint and when a new
 *   "mine" message arrives — for incoming messages we only auto-scroll if
 *   the user is already pinned near the bottom.
 */
import { useEffect, useMemo, useRef } from 'react';
import { Loader2Icon } from 'lucide-react';
import { useMessagesQuery } from '@/lib/queries/messaging';
import { useAuth } from '@/hooks/useAuth';
import { MessageBubble } from './MessageBubble';
import { OfferBubble } from './OfferBubble';
import { dayBucketKey, formatDaySeparator } from './relative-time';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n/messages';
import type { Message } from '@/lib/api/types';

interface Props {
  conversationId: string;
}

export function MessageList({ conversationId }: Props) {
  const { user } = useAuth();
  const {
    data,
    isLoading,
    isError,
    hasNextPage,
    isFetchingNextPage,
    fetchNextPage,
  } = useMessagesQuery(conversationId);

  const scrollRef = useRef<HTMLDivElement | null>(null);
  const lastSeenCountRef = useRef(0);
  const stickToBottomRef = useRef(true);

  // Flatten pages oldest-first.
  const messages = useMemo<Message[]>(() => {
    if (!data) return [];
    const all: Message[] = [];
    // pages[0] is the newest page; reverse so we get oldest → newest overall.
    for (let i = data.pages.length - 1; i >= 0; i--) {
      const page = data.pages[i];
      // Within a page the API returns newest-first too — reverse again.
      for (let j = page.data.length - 1; j >= 0; j--) {
        all.push(page.data[j]);
      }
    }
    return all;
  }, [data]);

  // Track whether the user is near the bottom so we know if it's safe to
  // auto-scroll on the next render.
  useEffect(() => {
    const el = scrollRef.current;
    if (!el) return;
    const onScroll = () => {
      const distanceFromBottom =
        el.scrollHeight - el.scrollTop - el.clientHeight;
      stickToBottomRef.current = distanceFromBottom < 120;
    };
    el.addEventListener('scroll', onScroll, { passive: true });
    return () => el.removeEventListener('scroll', onScroll);
  }, []);

  // Auto-scroll on first paint and when a new message is appended.
  useEffect(() => {
    const el = scrollRef.current;
    if (!el) return;
    const prevCount = lastSeenCountRef.current;
    const currentCount = messages.length;
    if (currentCount === 0) return;

    const isFirstPaint = prevCount === 0;
    const newestMessage = messages[currentCount - 1];
    const isOwnNewest = newestMessage?.sender_id === user?.id;

    if (isFirstPaint || isOwnNewest || stickToBottomRef.current) {
      el.scrollTop = el.scrollHeight;
    }
    lastSeenCountRef.current = currentCount;
  }, [messages, user?.id]);

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

  if (isError) {
    return (
      <p className="text-destructive p-6 text-center text-sm">
        {t('common.error', 'حدث خطأ، حاول مرة أخرى')}
      </p>
    );
  }

  if (messages.length === 0) {
    return (
      <p className="text-ink-500 px-6 py-10 text-center text-sm">
        {t('messaging.empty.view', 'ابدأ المحادثة بكتابة رسالتك الأولى.')}
      </p>
    );
  }

  return (
    <div
      ref={scrollRef}
      className="flex-1 space-y-2 overflow-y-auto p-4"
      aria-live="polite"
    >
      {hasNextPage ? (
        <div className="flex justify-center pb-2">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={() => fetchNextPage()}
            disabled={isFetchingNextPage}
          >
            {isFetchingNextPage
              ? t('common.loading', 'جاري التحميل…')
              : t('messaging.load_older', 'عرض الرسائل الأقدم')}
          </Button>
        </div>
      ) : null}

      {messages.map((message, index) => {
        const prev = messages[index - 1];
        const next = messages[index + 1];
        const isMine = message.sender_id === user?.id;
        const showAvatar =
          !isMine && (!prev || prev.sender_id !== message.sender_id);
        const isLastInOwnStreak =
          isMine && (!next || next.sender_id !== message.sender_id);
        const sameDayAsPrev =
          prev && dayBucketKey(prev.created_at) === dayBucketKey(message.created_at);

        // Offer messages render as a richer card. The backend always sets
        // `body` to a short summary ("اعرض X QAR") which we keep underneath
        // for screen-readers and timeline continuity.
        const isOffer = message.type === 'offer' && message.offer;

        return (
          <div key={message.id} className="space-y-2">
            {!sameDayAsPrev ? (
              <div className="my-3 flex items-center gap-3">
                <span className="bg-ink-200 h-px flex-1" />
                <span className="text-ink-500 text-[11px]">
                  {formatDaySeparator(message.created_at)}
                </span>
                <span className="bg-ink-200 h-px flex-1" />
              </div>
            ) : null}
            {isOffer && message.offer ? (
              <OfferBubble offer={message.offer} isMine={isMine} />
            ) : (
              <MessageBubble
                message={message}
                isMine={isMine}
                showAvatar={showAvatar}
                isLastInOwnStreak={isLastInOwnStreak}
              />
            )}
          </div>
        );
      })}
    </div>
  );
}
