'use client';

/**
 * One saved search (381:8815): search tile, name, when it was saved, one chip
 * per kept filter, then "View Result" (route restoration) and delete with a
 * confirmation.
 */
import { useMemo, useState } from 'react';
import Link from 'next/link';
import { toast } from 'sonner';
import { Loader2, Search, Trash2 } from 'lucide-react';

import { Button, buttonVariants } from '@/components/design-system/Button';
import { Icon } from '@/components/design-system/Icon';
import { Modal } from '@/components/design-system/Modal';
import { formatRelativeTime } from '@/components/messaging/relative-time';
import { useDeleteSavedSearchMutation } from '@/lib/queries/search';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import { ApiClientError } from '@/lib/api/auth';
import type { SavedSearch } from '@/lib/api/types';

import { savedRowButtonClass } from './SavedAdRow';
import { savedSearchChips, savedSearchHref } from './saved-search-params';

interface Props {
  search: SavedSearch;
}

export function SavedSearchCard({ search }: Props) {
  const [confirmOpen, setConfirmOpen] = useState(false);
  const deleteMutation = useDeleteSavedSearchMutation();

  const href = useMemo(() => savedSearchHref(search.query_params), [search]);
  const chips = useMemo(() => savedSearchChips(search.query_params), [search]);

  const onDelete = () => {
    deleteMutation.mutate(search.id, {
      onSuccess: () => {
        toast.success(
          t('account.saved_searches.delete_success', 'تم حذف البحث المحفوظ'),
        );
        setConfirmOpen(false);
      },
      onError: (err) => {
        if (err instanceof ApiClientError) {
          toast.error(
            translateMaybeKey(`search.errors.${err.code.toLowerCase()}`) ||
              translateMaybeKey('search.errors.delete_failed') ||
              err.message,
          );
        } else {
          toast.error(t('search.errors.delete_failed'));
        }
      },
    });
  };

  return (
    <article className="rounded-qb-2xl border border-qb-line bg-qb-surface p-4 font-qb shadow-qb-card qb-tablet:p-6">
      <div className="flex items-start gap-3.5">
        <span
          aria-hidden="true"
          className="flex size-12 shrink-0 items-center justify-center rounded-qb-xl border border-qb-line bg-qb-surface text-qb-ink-body shadow-qb-card qb-tablet:size-[60px]"
        >
          <Icon icon={Search} size="lg" />
        </span>
        <div className="min-w-0 flex-1">
          <h3 className="truncate text-qb-body font-semibold tracking-normal text-qb-ink qb-tablet:text-qb-h5">
            <bdi>{search.name}</bdi>
          </h3>
          <p className="mt-2 text-qb-label font-medium text-qb-breadcrumb qb-tablet:text-qb-caption">
            {t('account.saved_searches.saved_ago', { when: formatRelativeTime(search.created_at) })}
          </p>
        </div>
        <button
          type="button"
          onClick={() => setConfirmOpen(true)}
          aria-label={t('account.saved_searches.delete_label', { name: search.name })}
          className={savedRowButtonClass}
        >
          <Trash2 aria-hidden="true" />
        </button>
      </div>

      <ul className="mt-4 flex flex-wrap gap-2.5 qb-tablet:mt-5 qb-tablet:gap-[15px]">
        {chips.length > 0 ? (
          chips.map((chip) => (
            <li
              key={chip.label}
              className="inline-flex min-h-[33px] items-center gap-1 rounded-qb-sm bg-qb-fill px-2.5 text-qb-label qb-tablet:text-qb-caption"
            >
              <span className="text-qb-ink-subtle">{chip.label}:</span>
              <span className="font-medium text-qb-ink-title capitalize">{chip.value}</span>
            </li>
          ))
        ) : (
          <li className="text-qb-label text-qb-ink-subtle">{t('search.title_all', 'كل الإعلانات')}</li>
        )}
      </ul>

      <div className="mt-4 flex items-center justify-end border-t border-qb-line pt-4 qb-tablet:mt-[17px] qb-tablet:pt-[17px]">
        <Link href={href} className={cn(buttonVariants({ size: 'sm' }), 'h-10 rounded-qb-sm px-4')}>
          {t('account.saved_searches.view_results')}
        </Link>
      </div>

      <Modal
        open={confirmOpen}
        onOpenChange={(open) => {
          if (!open && !deleteMutation.isPending) setConfirmOpen(false);
        }}
        title={t('account.saved_searches.delete_confirm_title')}
        description={t('account.saved_searches.delete_confirm_body')}
      >
        <div className="mt-2 grid grid-cols-2 gap-3 qb-tablet:gap-5">
          <Button size="sm" disabled={deleteMutation.isPending} onClick={onDelete}>
            {deleteMutation.isPending ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
            {t('account.saved_searches.delete', 'حذف')}
          </Button>
          <Button variant="muted" size="sm" disabled={deleteMutation.isPending} onClick={() => setConfirmOpen(false)}>
            {t('search.save_search.cancel', 'إلغاء')}
          </Button>
        </div>
      </Modal>
    </article>
  );
}
