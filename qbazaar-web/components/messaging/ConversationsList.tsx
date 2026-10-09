'use client';

/**
 * Inbox pane of messages.html (370:18776): the user's name with the select
 * and delete icons, the "Search" field that filters the threads by name as
 * you type, the "Message" label and one row per conversation, with a manual
 * "load more" for further pages. In select mode every row gets a checkbox
 * and the trash hides the chosen threads after "Delete this conversation?".
 *
 * The active conversation is driven by the parent (URL `?c=` query param).
 * Real-time updates are handled by the shared `useUserChannel` mounted in
 * the SiteHeader: it invalidates `messagingKeys.lists()` which re-fetches
 * this component's query.
 */
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { MessageSquareX, Search, SquareCheckBig, Trash2 } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { focusRing } from '@/components/design-system/focus-ring';
import { PanelState } from '@/components/account/PanelState';
import { apiErrorMessage } from '@/components/account/api-error-message';
import { useAuth } from '@/hooks/useAuth';
import { useConversationsQuery, useHideConversationsMutation } from '@/lib/queries/messaging';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

import { ChatConfirmDialog } from './ChatConfirmDialog';
import { ConversationRow } from './ConversationRow';

interface Props {
  activeConversationId: string | null;
  onSelect: (id: string) => void;
}

const PAGE_SIZE = 20;

const headerIcon = cn(
  'flex size-8 cursor-pointer items-center justify-center rounded-qb-md disabled:cursor-not-allowed [&_svg]:size-5',
  focusRing,
);

export function ConversationsList({ activeConversationId, onSelect }: Props) {
  const { user } = useAuth();
  const [page, setPage] = useState(1);
  const [query, setQuery] = useState('');
  const [selecting, setSelecting] = useState(false);
  const [selected, setSelected] = useState<ReadonlySet<string>>(() => new Set());
  const [confirmOpen, setConfirmOpen] = useState(false);
  const hide = useHideConversationsMutation();
  const { data, isLoading, isError } = useConversationsQuery({ page, per_page: PAGE_SIZE });

  const rows = useMemo(() => data?.data ?? [], [data]);
  const needle = query.trim().toLocaleLowerCase();
  const visibleRows = needle
    ? rows.filter((row) => row.other_participant.full_name.toLocaleLowerCase().includes(needle))
    : rows;
  const lastPage = data?.meta.last_page ?? 1;
  const hasMore = page < lastPage;
  const selectedIds = rows.filter((row) => selected.has(row.id)).map((row) => row.id);

  const toggleSelecting = () => {
    setSelecting((value) => !value);
    setSelected(new Set());
  };

  const toggleRow = (id: string, checked: boolean) =>
    setSelected((current) => {
      const next = new Set(current);
      if (checked) next.add(id);
      else next.delete(id);
      return next;
    });

  const confirmHide = () =>
    hide.mutate(selectedIds, {
      onSuccess: () => {
        toast.success(t('messaging.delete.done', { count: selectedIds.length }));
        setConfirmOpen(false);
        setSelecting(false);
        setSelected(new Set());
      },
      onError: (err) => toast.error(apiErrorMessage(err)),
    });

  return (
    <div className="flex h-full min-h-0 flex-col p-6">
      <div className="mb-[18px] flex items-center justify-between gap-3">
        <p className="truncate text-qb-h3 font-semibold text-qb-ink">{user?.full_name}</p>
        <div className="flex shrink-0 items-center gap-1">
          <button
            type="button"
            onClick={toggleSelecting}
            aria-pressed={selecting}
            aria-label={t('messaging.select_mode')}
            className={cn(headerIcon, selecting ? 'text-qb-brand' : 'text-qb-ink-subtle hover:text-qb-ink')}
          >
            <SquareCheckBig aria-hidden="true" strokeWidth={1.6} />
          </button>
          <button
            type="button"
            onClick={() => setConfirmOpen(true)}
            disabled={!selecting || selectedIds.length === 0}
            aria-label={t('messaging.delete.selected')}
            className={cn(headerIcon, 'text-qb-ink-subtle enabled:hover:text-qb-danger')}
          >
            <Trash2 aria-hidden="true" strokeWidth={1.6} />
          </button>
        </div>
      </div>

      <label className="mb-5 flex items-center gap-2.5 rounded-qb-lg border border-qb-line px-3.5 py-3 focus-within:border-qb-brand">
        <Search aria-hidden="true" className="size-5 shrink-0 text-qb-icon-faint" strokeWidth={1.6} />
        <input
          type="search"
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          placeholder={t('messaging.search_placeholder')}
          aria-label={t('messaging.search_label')}
          className="min-w-0 flex-1 bg-transparent font-qb text-qb-body-sm text-qb-ink outline-none placeholder:text-qb-placeholder"
        />
      </label>

      <h2 className="mb-3 text-qb-body font-semibold tracking-normal text-qb-ink-secondary">{t('messaging.group_label')}</h2>

      {isLoading && !data ? (
        <PanelState loading />
      ) : isError ? (
        <PanelState loading={false} message={t('common.error', 'حدث خطأ، حاول مرة أخرى')} />
      ) : rows.length === 0 ? (
        <p className="px-3 py-10 text-center text-qb-body-sm text-qb-ink-subtle">{t('messaging.empty.inbox_short')}</p>
      ) : visibleRows.length === 0 ? (
        <div role="status" className="flex flex-col items-center px-3 py-10 text-center">
          <MessageSquareX aria-hidden="true" className="mb-2.5 size-9 text-qb-ink-disabled" strokeWidth={1.5} />
          <p className="text-qb-body-sm font-medium text-qb-ink-body">{t('messaging.search_empty_title')}</p>
          <p className="mt-1 text-qb-label text-qb-ink-subtle">{t('messaging.search_empty_body')}</p>
        </div>
      ) : (
        <>
          <ul className="-mx-1 min-h-0 flex-1 overflow-y-auto px-1">
            {visibleRows.map((conversation) => (
              <li key={conversation.id} className="mb-1.5">
                <ConversationRow
                  conversation={conversation}
                  active={conversation.id === activeConversationId}
                  onSelect={onSelect}
                  selection={
                    selecting
                      ? { checked: selected.has(conversation.id), onChange: (checked) => toggleRow(conversation.id, checked) }
                      : undefined
                  }
                />
              </li>
            ))}
          </ul>
          {hasMore && !needle ? (
            <div className="border-t border-qb-line pt-3">
              <Button variant="outline" size="sm" fullWidth onClick={() => setPage((p) => p + 1)} disabled={isLoading}>
                {isLoading ? t('common.loading', 'جاري التحميل…') : t('common.view_all', 'عرض الكل')}
              </Button>
            </div>
          ) : null}
        </>
      )}

      <ChatConfirmDialog
        variant="delete"
        open={confirmOpen}
        onOpenChange={setConfirmOpen}
        title={t('messaging.delete.title')}
        body={t('messaging.delete.body')}
        confirmLabel={t('messaging.delete.confirm')}
        onConfirm={confirmHide}
        pending={hide.isPending}
      />
    </div>
  );
}
