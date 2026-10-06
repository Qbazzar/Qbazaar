'use client';

/**
 * Inbox (370:18776) and chat (365:14788) side by side from 1001 px; below
 * that the page works in two steps, inbox then chat (604:33590 → 604:33673).
 *
 * URL `?c={conversationId}` drives which thread is open.
 */
import { useCallback, useEffect } from 'react';
import { parseAsString, useQueryState } from 'nuqs';
import { MessagesSquare } from 'lucide-react';

import { Breadcrumb } from '@/components/design-system/Breadcrumb';
import { EmptyState } from '@/components/design-system/EmptyState';
import { Icon } from '@/components/design-system/Icon';
import { pageGutter } from '@/components/design-system/page-gutter';
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
    <div className="font-qb">
      <h1 className="sr-only">{t('messaging.title', 'صندوق رسائلي')}</h1>
      <div className={cn('mx-auto max-w-[1440px] pt-10 qb-tablet:pt-[72px] qb-tablet:pb-[73px] qb-desktop:pt-[65px]', pageGutter)}>
        <Breadcrumb
          items={[{ label: t('home.breadcrumb'), href: '/' }, { label: t('account.nav.messages') }]}
          className="hidden qb-tablet:block"
        />
      </div>

      <div className="border-y border-qb-line bg-qb-surface qb-tablet:border-y-0">
        <div className="mx-auto flex h-[calc(100dvh-124px)] min-h-[480px] max-w-[1440px] qb-tablet:h-[calc(100dvh-261px)] qb-desktop:h-[min(753px,calc(100dvh-254px))]">
          <aside
            aria-label={t('account.nav.messages')}
            className={cn(
              'min-w-0 flex-1 flex-col shadow-qb-soft qb-desktop:flex qb-desktop:w-[478px] qb-desktop:flex-none',
              hasActive ? 'hidden' : 'flex',
            )}
          >
            <ConversationsList activeConversationId={activeId || null} onSelect={handleSelect} />
          </aside>

          <section
            aria-label={t('messaging.chat_label')}
            className={cn('min-w-0 flex-1 flex-col shadow-qb-soft', hasActive ? 'flex' : 'hidden qb-desktop:flex')}
          >
            {hasActive ? (
              <ConversationView conversationId={activeId} onBack={handleBack} />
            ) : (
              <div className="flex flex-1 items-center justify-center">
                <EmptyState
                  icon={<Icon icon={MessagesSquare} size="lg" />}
                  title={t('messaging.empty.view', 'اختر محادثة لعرض الرسائل.')}
                  headingLevel="h2"
                />
              </div>
            )}
          </section>
        </div>
      </div>
    </div>
  );
}
