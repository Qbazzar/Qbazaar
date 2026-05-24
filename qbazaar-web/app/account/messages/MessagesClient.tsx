'use client';

/**
 * Inbox split layout.
 *
 * URL `?c={conversationId}` is the source of truth for which thread is open
 * — nuqs keeps the query string in sync with the Zustand mirror so deep
 * links and the browser back-button both behave naturally.
 *
 * On lg+ both panes are visible side by side (40/60). On smaller screens
 * we toggle between list and view depending on whether a conversation is
 * selected.
 */
import { useCallback, useEffect } from 'react';
import { parseAsString, useQueryState } from 'nuqs';
import { ConversationsList } from '@/components/messaging/ConversationsList';
import { ConversationView } from '@/components/messaging/ConversationView';
import { useMessagingStore } from '@/store/messaging';
import { cn } from '@/lib/utils';
import { t } from '@/lib/i18n/messages';

export function MessagesClient() {
  const [activeId, setActiveId] = useQueryState(
    'c',
    parseAsString.withDefault(''),
  );

  const setActiveConversation = useMessagingStore(
    (s) => s.setActiveConversation,
  );

  // Mirror URL → store on every change so non-React callers (Echo hooks)
  // can read the current selection without a hook.
  useEffect(() => {
    setActiveConversation(activeId || null);
    return () => setActiveConversation(null);
  }, [activeId, setActiveConversation]);

  const handleSelect = useCallback(
    (id: string) => {
      void setActiveId(id);
    },
    [setActiveId],
  );

  const handleBack = useCallback(() => {
    void setActiveId('');
  }, [setActiveId]);

  const hasActive = Boolean(activeId);

  return (
    <div className="space-y-4">
      <header className="space-y-1">
        <p className="text-coral text-xs font-bold uppercase tracking-[0.18em]">
          {t('account.nav.messages', 'الرسائل')}
        </p>
        <h1 className="font-display text-ink-900 text-3xl md:text-4xl">
          {t('messaging.title', 'صندوق رسائلي')}
        </h1>
      </header>

      <div
        className={cn(
          'bg-card ring-foreground/10 grid h-[calc(100svh-260px)] min-h-[480px] overflow-hidden rounded-2xl ring-1',
          'lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]',
        )}
      >
        {/* LIST */}
        <aside
          className={cn(
            'border-ink-200 h-full overflow-hidden lg:border-e',
            hasActive ? 'hidden lg:block' : 'block',
          )}
        >
          <ConversationsList
            activeConversationId={activeId || null}
            onSelect={handleSelect}
          />
        </aside>

        {/* VIEW */}
        <section
          className={cn(
            'h-full overflow-hidden',
            hasActive ? 'block' : 'hidden lg:block',
          )}
        >
          {hasActive ? (
            <ConversationView
              conversationId={activeId}
              onBack={handleBack}
            />
          ) : (
            <div className="text-ink-500 hidden h-full items-center justify-center p-6 text-center text-sm lg:flex">
              {t('messaging.empty.view', 'اختر محادثة لعرض الرسائل.')}
            </div>
          )}
        </section>
      </div>
    </div>
  );
}
