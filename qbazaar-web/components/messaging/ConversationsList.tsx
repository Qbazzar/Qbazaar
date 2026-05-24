'use client';

/**
 * Left pane of the inbox — paginated list of conversations.
 *
 * The active conversation is driven by the parent (URL `?c=` query param).
 * Pagination is a manual "load more" button so the implementation stays
 * straightforward — infinite scroll on the list isn't a Sprint 8 ask.
 *
 * Real-time updates are handled by the shared `useUserChannel` mounted in
 * the SiteHeader: it invalidates `messagingKeys.lists()` which re-fetches
 * this component's query.
 */
import { useState } from 'react';
import { Loader2Icon, InboxIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useConversationsQuery } from '@/lib/queries/messaging';
import { ConversationRow } from './ConversationRow';
import { t } from '@/lib/i18n/messages';

interface Props {
  activeConversationId: string | null;
  onSelect: (id: string) => void;
}

const PAGE_SIZE = 20;

export function ConversationsList({ activeConversationId, onSelect }: Props) {
  const [page, setPage] = useState(1);
  const { data, isLoading, isError } = useConversationsQuery({
    page,
    per_page: PAGE_SIZE,
  });

  const rows = data?.data ?? [];
  const lastPage = data?.meta.last_page ?? 1;
  const hasMore = page < lastPage;

  if (isLoading && !data) {
    return (
      <div className="flex h-40 items-center justify-center" role="status">
        <Loader2Icon
          className="text-muted-foreground size-5 animate-spin"
          aria-hidden
        />
      </div>
    );
  }

  if (isError) {
    return (
      <p className="text-destructive p-4 text-center text-sm">
        {t('common.error', 'حدث خطأ، حاول مرة أخرى')}
      </p>
    );
  }

  if (rows.length === 0) {
    return (
      <div className="text-ink-700 flex flex-col items-center justify-center gap-3 p-8 text-center">
        <span className="bg-cream-200 text-coral grid size-12 place-items-center rounded-full">
          <InboxIcon className="size-5" aria-hidden />
        </span>
        <p className="text-sm">
          {t(
            'messaging.empty.list',
            'ابدأ محادثة بالتواصل مع بائع',
          )}
        </p>
      </div>
    );
  }

  return (
    <div className="flex h-full flex-col">
      <ul className="flex-1 space-y-1 overflow-y-auto p-2">
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
        <div className="border-ink-200 border-t p-3">
          <Button
            type="button"
            variant="outline"
            size="sm"
            className="w-full rounded-full"
            onClick={() => setPage((p) => p + 1)}
            disabled={isLoading}
          >
            {isLoading
              ? t('common.loading', 'جاري التحميل…')
              : t('common.view_all', 'عرض الكل')}
          </Button>
        </div>
      ) : null}
    </div>
  );
}
