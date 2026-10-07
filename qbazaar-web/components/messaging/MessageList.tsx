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
 * - Screen readers hear each incoming message from a separate live region,
 *   not from the thread itself, so loading older messages stays silent.
 */
import { useEffect, useMemo, useRef, useState } from 'react';
import { ShieldCheck } from 'lucide-react';
import { useMessagesQuery } from '@/lib/queries/messaging';
import { useAuth } from '@/hooks/useAuth';
import { MessageBubble } from './MessageBubble';
import { DealCardForMessage, isDealMessage } from '@/components/orders/DealCardForMessage';
import { dayBucketKey, formatDaySeparator } from './relative-time';
import { Button } from '@/components/design-system/Button';
import { focusRing } from '@/components/design-system/focus-ring';
import { PanelState } from '@/components/account/PanelState';
import { cn } from '@/lib/utils';
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

  const incoming = useIncomingAnnouncement(conversationId, messages, user?.id);

  if (isLoading) {
    return <PanelState loading className="flex flex-1 items-center" />;
  }

  if (isError) {
    return <PanelState loading={false} message={t('common.error', 'حدث خطأ، حاول مرة أخرى')} className="flex-1" />;
  }

  return (
    <div
      ref={scrollRef}
      // Focusable so the thread can be scrolled from the keyboard.
      tabIndex={0}
      role="group"
      aria-label={t('messaging.thread_label')}
      className={cn(
        'flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto px-[21px] py-6 qb-tablet:px-6 qb-desktop:px-10',
        focusRing,
        '-outline-offset-2',
      )}
    >
      <SafetyNotice />

      {messages.length === 0 ? (
        <p className="px-6 py-10 text-center text-qb-caption text-qb-ink-subtle">
          {t('messaging.empty.view', 'ابدأ المحادثة بكتابة رسالتك الأولى.')}
        </p>
      ) : null}

      {hasNextPage ? (
        <div className="flex justify-center">
          <Button variant="ghost" size="sm" onClick={() => fetchNextPage()} disabled={isFetchingNextPage}>
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

        // Offer and purchase-request messages render as cards.
        const isDeal = isDealMessage(message);

        return (
          <div key={message.id} className="flex flex-col gap-4">
            {!sameDayAsPrev ? (
              <p className="self-center rounded-qb-xs bg-qb-fill px-4 py-2 font-qb-label text-qb-micro font-medium text-qb-ink-secondary qb-tablet:text-qb-body qb-desktop:text-qb-h5">
                {formatDaySeparator(message.created_at)}
              </p>
            ) : null}
            {isDeal ? (
              <DealCardForMessage message={message} isMine={isMine} />
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
      <p role="status" className="sr-only">
        {incoming}
      </p>
    </div>
  );
}

/**
 * Text for the live region: the newest message, once, when someone else's
 * lands at the end of the open thread. The first page and older pages are
 * history, not news.
 */
function useIncomingAnnouncement(conversationId: string, messages: Message[], viewerId: string | undefined): string {
  const [announcement, setAnnouncement] = useState('');
  const newestRef = useRef<{ conversationId: string; messageId: string } | null>(null);

  useEffect(() => {
    const newest = messages[messages.length - 1];
    if (!newest) return;
    const previous = newestRef.current;
    newestRef.current = { conversationId, messageId: newest.id };
    if (previous?.conversationId !== conversationId) {
      setAnnouncement('');
      return;
    }
    if (previous.messageId !== newest.id && newest.sender_id !== viewerId) {
      setAnnouncement(t('messaging.incoming_message', { name: newest.sender.full_name, message: newest.body }));
    }
  }, [conversationId, messages, viewerId]);

  return announcement;
}

/** "Safe Pay" reminder at the top of every chat (365:14788). */
function SafetyNotice() {
  return (
    <p className="mx-auto flex max-w-[626px] items-start gap-3 rounded-qb-lg border border-qb-brand bg-qb-brand-soft px-4 py-3.5 text-qb-label text-qb-brand-on-soft qb-tablet:items-center qb-tablet:rounded-qb-md qb-tablet:px-6 qb-tablet:text-qb-body">
      <ShieldCheck className="mt-0.5 size-5 shrink-0 text-qb-brand qb-tablet:mt-0" aria-hidden="true" />
      {t('messaging.safety_notice')}
    </p>
  );
}
