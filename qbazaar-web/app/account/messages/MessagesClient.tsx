'use client';

/**
 * Inbox split layout — QBFront port (source: QBFront/messages.html).
 *
 * URL `?c={conversationId}` drives which thread is open. The grid follows
 * `.messages-page` (340px list · flex view) and mobile collapses to a single
 * pane via the QBFront breakpoint rule.
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
    <div className="container" style={{ paddingTop: 24, paddingBottom: 48 }}>
      <header style={{ marginBottom: 20 }}>
        <h1 className="cat-page__title">{t('messaging.title', 'صندوق رسائلي')}</h1>
      </header>

      <div className="messages-page">
        <aside
          className={cn(
            'thread-list',
            hasActive ? 'hidden lg:flex' : 'flex',
          )}
        >
          <ConversationsList
            activeConversationId={activeId || null}
            onSelect={handleSelect}
          />
        </aside>

        <section className={cn('conv', hasActive ? 'flex' : 'hidden lg:flex')}>
          {hasActive ? (
            <ConversationView
              conversationId={activeId}
              onBack={handleBack}
            />
          ) : (
            <div
              className="text-muted hidden h-full items-center justify-center p-6 text-center text-sm lg:flex"
              style={{ minHeight: 480 }}
            >
              {t('messaging.empty.view', 'اختر محادثة لعرض الرسائل.')}
            </div>
          )}
        </section>
      </div>
    </div>
  );
}
