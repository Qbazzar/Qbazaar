'use client';

/**
 * The messages card of messages.html: inbox (370:18776) and chat (365:14788)
 * side by side from 1001 px; below that the page works in two steps, inbox
 * then chat (604:33590 → 604:33673).
 *
 * URL `?c={conversationId}` drives which thread is open.
 */
import { useCallback, useEffect, useRef } from 'react';
import Link from 'next/link';
import { parseAsString, useQueryState } from 'nuqs';

import { focusRing } from '@/components/design-system/focus-ring';
import { pageGutter } from '@/components/design-system/page-gutter';
import { ConversationsList } from '@/components/messaging/ConversationsList';
import { ConversationView } from '@/components/messaging/ConversationView';
import { useMessagingStore } from '@/store/messaging';
import { cn } from '@/lib/utils';
import { t } from '@/lib/i18n/messages';

/** Inbox and chat side by side from the desktop layout (`qb-desktop`). */
const TWO_PANES_QUERY = '(min-width: 1001px)';

export function MessagesClient() {
  const [activeId, setActiveId] = useQueryState(
    'c',
    parseAsString.withDefault(''),
  );

  const setActiveConversation = useMessagingStore(
    (s) => s.setActiveConversation,
  );

  useEffect(() => {
    setActiveConversation(activeId || null);
    return () => setActiveConversation(null);
  }, [activeId, setActiveConversation]);

  // Below 1001 px the pane that held focus is hidden on each step, so focus
  // follows the user: into the chat when a thread opens, back to its row.
  const inboxRef = useRef<HTMLElement>(null);
  const chatRef = useRef<HTMLElement>(null);
  const lastOpenedRef = useRef<string>('');
  const pendingFocusRef = useRef<'chat' | 'inbox' | null>(null);

  useEffect(() => {
    const target = pendingFocusRef.current;
    pendingFocusRef.current = null;
    if (target === 'chat') chatRef.current?.focus();
    if (target === 'inbox') {
      inboxRef.current
        ?.querySelector<HTMLElement>(`[data-conversation-id="${CSS.escape(lastOpenedRef.current)}"]`)
        ?.focus();
    }
  }, [activeId]);

  const handleSelect = useCallback(
    (id: string) => {
      if (!window.matchMedia(TWO_PANES_QUERY).matches) pendingFocusRef.current = 'chat';
      void setActiveId(id);
    },
    [setActiveId],
  );

  const handleBack = useCallback(() => {
    lastOpenedRef.current = activeId;
    pendingFocusRef.current = 'inbox';
    void setActiveId('');
  }, [activeId, setActiveId]);

  const hasActive = Boolean(activeId);

  return (
    <div className={cn('mx-auto max-w-[1440px] py-[clamp(16px,3vw,28px)] font-qb', pageGutter)}>
      <h1 className="sr-only">{t('messaging.title', 'صندوق رسائلي')}</h1>
      <div className="flex h-[calc(100dvh-136px)] min-h-[520px] overflow-hidden rounded-qb-xl border border-qb-line bg-qb-surface qb-desktop:h-[min(800px,calc(100dvh-150px))]">
        <aside
          ref={inboxRef}
          aria-label={t('account.nav.messages')}
          className={cn(
            'min-w-0 flex-1 flex-col qb-desktop:flex qb-desktop:min-w-[280px] qb-desktop:flex-[1_1_320px] qb-desktop:border-e qb-desktop:border-qb-line',
            hasActive ? 'hidden' : 'flex',
          )}
        >
          <ConversationsList activeConversationId={activeId || null} onSelect={handleSelect} />
        </aside>

        <section
          ref={chatRef}
          tabIndex={-1}
          aria-label={t('messaging.chat_label')}
          className={cn(
            'min-w-0 flex-1 flex-col outline-none qb-desktop:min-w-[300px] qb-desktop:flex-[2_1_440px]',
            hasActive ? 'flex' : 'hidden qb-desktop:flex',
          )}
        >
          {hasActive ? (
            <ConversationView conversationId={activeId} onBack={handleBack} />
          ) : (
            <EmptyChatPane />
          )}
        </section>
      </div>
    </div>
  );
}

/** The "Iconly Light Chat" glyph of 370:18776. */
function ChatBubbleIcon({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" className={className}>
      <path
        d="M12 2.6c5.2 0 9.4 4.03 9.4 9s-4.2 9-9.4 9c-1.05 0-2.08-.16-3.05-.48-.35-.11-.72-.08-1.05.08l-2.4 1.2c-.63.31-1.37-.13-1.37-.83l-.02-2.1a1.2 1.2 0 0 0-.4-.9A8.9 8.9 0 0 1 2.6 11.6c0-4.97 4.2-9 9.4-9Z"
        stroke="currentColor"
        strokeWidth="1.5"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      <path
        d="M7.9 12.7a1.05 1.05 0 1 0 0-2.1 1.05 1.05 0 0 0 0 2.1Zm4.1 0a1.05 1.05 0 1 0 0-2.1 1.05 1.05 0 0 0 0 2.1Zm4.1 0a1.05 1.05 0 1 0 0-2.1 1.05 1.05 0 0 0 0 2.1Z"
        fill="currentColor"
      />
    </svg>
  );
}

/**
 * Right pane while no chat is open (370:18776, also with conversations
 * listed): the tilted icon tile, "No Messages Yet" and "Browse Ads".
 */
function EmptyChatPane() {
  return (
    <div className="flex flex-1 flex-col items-center justify-center px-6 text-center font-qb">
      <span
        aria-hidden="true"
        className="flex size-[93px] -rotate-[2.34deg] items-center justify-center rounded-qb-2xl border border-qb-line bg-qb-chat-empty-tile text-qb-chat-empty-icon shadow-qb-chat-empty-tile"
      >
        <ChatBubbleIcon className="size-10 rotate-[0.69deg]" />
      </span>
      <h2 className="mt-[26px] text-[22px] leading-[1.5] font-medium tracking-normal text-qb-ink-muted">
        {t('messaging.empty.no_messages_title')}
      </h2>
      <p className="mt-[5px] max-w-[806px] text-qb-h5 leading-[1.5] text-qb-ink-disabled">
        {t('messaging.empty.no_messages_body')}
      </p>
      <Link
        href="/ads"
        className={cn(
          'mt-4 flex h-10 w-[157px] items-center justify-center rounded-qb-sm bg-qb-brand text-qb-caption font-semibold text-qb-chat-empty-cta hover:bg-qb-brand-hover',
          focusRing,
        )}
      >
        {t('account.saved_searches.browse_ads')}
      </Link>
    </div>
  );
}
