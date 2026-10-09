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
import { MessagesSquare } from 'lucide-react';

import { focusRing } from '@/components/design-system/focus-ring';
import { pageGutter } from '@/components/design-system/page-gutter';
import { ConversationsList } from '@/components/messaging/ConversationsList';
import { ConversationView } from '@/components/messaging/ConversationView';
import { useConversationsQuery } from '@/lib/queries/messaging';
import { useMessagingStore } from '@/store/messaging';
import { cn } from '@/lib/utils';
import { t } from '@/lib/i18n/messages';

/** Inbox and chat side by side from the desktop layout (`qb-desktop`). */
const TWO_PANES_QUERY = '(min-width: 1001px)';
/** The inbox's page size: the first page doubles as the "is the inbox empty" check. */
const INBOX_PAGE_SIZE = 20;

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
  // Same key as the inbox's first page, so this costs no extra request.
  const { data: firstPage } = useConversationsQuery({ page: 1, per_page: INBOX_PAGE_SIZE });
  const inboxEmpty = firstPage?.data.length === 0;

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
            <EmptyChatPane inboxEmpty={inboxEmpty} />
          )}
        </section>
      </div>
    </div>
  );
}

/** Right pane before a chat is open (370:18776): "No Messages Yet" while the inbox is empty. */
function EmptyChatPane({ inboxEmpty }: { inboxEmpty: boolean }) {
  return (
    <div className="flex flex-1 flex-col items-center justify-center gap-4 px-6 text-center">
      <span
        aria-hidden="true"
        className="flex size-24 items-center justify-center rounded-full bg-qb-brand-soft text-qb-brand"
      >
        <MessagesSquare className="size-10" strokeWidth={1.6} />
      </span>
      <h2 className="text-qb-h3 font-semibold tracking-normal text-qb-ink">
        {t(inboxEmpty ? 'messaging.empty.no_messages_title' : 'messaging.empty.pick_title')}
      </h2>
      <p className="max-w-[420px] text-qb-body leading-[1.6] text-qb-ink-subtle">
        {t(inboxEmpty ? 'messaging.empty.no_messages_body' : 'messaging.empty.view')}
      </p>
      {inboxEmpty ? (
        <Link
          href="/ads"
          className={cn(
            'mt-2 rounded-qb-lg bg-qb-brand px-8 py-3.5 text-qb-body-sm font-semibold text-qb-on-brand hover:bg-qb-brand-hover',
            focusRing,
          )}
        >
          {t('account.saved_searches.browse_ads')}
        </Link>
      ) : null}
    </div>
  );
}
