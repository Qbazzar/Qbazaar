'use client';

/**
 * Inbox pane (370:18776): the user's name, the "Messages" group label and one
 * row per conversation, with a manual "load more" for further pages.
 *
 * The active conversation is driven by the parent (URL `?c=` query param).
 * Real-time updates are handled by the shared `useUserChannel` mounted in
 * the SiteHeader: it invalidates `messagingKeys.lists()` which re-fetches
 * this component's query.
 */
import { useState } from 'react';
import Link from 'next/link';
import { Inbox } from 'lucide-react';

import { Button, buttonVariants } from '@/components/design-system/Button';
import { EmptyState } from '@/components/design-system/EmptyState';
import { Icon } from '@/components/design-system/Icon';
import { PanelState } from '@/components/account/PanelState';
import { useAuth } from '@/hooks/useAuth';
import { useConversationsQuery } from '@/lib/queries/messaging';
import { t } from '@/lib/i18n/messages';

import { ConversationRow } from './ConversationRow';

interface Props {
  activeConversationId: string | null;
  onSelect: (id: string) => void;
}

const PAGE_SIZE = 20;

export function ConversationsList({ activeConversationId, onSelect }: Props) {
  const { user } = useAuth();
  const [page, setPage] = useState(1);
  const { data, isLoading, isError } = useConversationsQuery({
    page,
    per_page: PAGE_SIZE,
  });

  const rows = data?.data ?? [];
  const lastPage = data?.meta.last_page ?? 1;
  const hasMore = page < lastPage;

  return (
    <div className="flex h-full min-h-0 flex-col">
      <div className="px-[17px] pt-6 qb-desktop:px-8">
        <p className="truncate text-qb-h5 font-semibold text-qb-ink qb-desktop:text-qb-h3">{user?.full_name}</p>
        <h2 className="mt-6 text-qb-body font-semibold tracking-normal text-qb-ink-secondary qb-desktop:mt-8">
          {t('account.nav.messages')}
        </h2>
      </div>

      {isLoading && !data ? (
        <PanelState loading />
      ) : isError ? (
        <PanelState loading={false} message={t('common.error', 'حدث خطأ، حاول مرة أخرى')} />
      ) : rows.length === 0 ? (
        <EmptyState
          icon={<Icon icon={Inbox} size="lg" />}
          title={t('messaging.empty.list', 'ابدأ محادثة بالتواصل مع بائع')}
          headingLevel="h3"
          action={
            <Link href="/ads" className={buttonVariants({ size: 'sm' })}>
              {t('favorites.empty.cta', 'تصفّح الإعلانات')}
            </Link>
          }
        />
      ) : (
        <>
          <ul className="mt-3 min-h-0 flex-1 overflow-y-auto pb-4">
            {rows.map((conversation) => (
              <li key={conversation.id}>
                <ConversationRow
                  conversation={conversation}
                  active={conversation.id === activeConversationId}
                  onSelect={onSelect}
                />
              </li>
            ))}
          </ul>
          {hasMore ? (
            <div className="border-t border-qb-line p-3">
              <Button
                variant="outline"
                size="sm"
                fullWidth
                onClick={() => setPage((p) => p + 1)}
                disabled={isLoading}
              >
                {isLoading
                  ? t('common.loading', 'جاري التحميل…')
                  : t('common.view_all', 'عرض الكل')}
              </Button>
            </div>
          ) : null}
        </>
      )}
    </div>
  );
}
