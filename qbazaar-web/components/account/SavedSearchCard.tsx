'use client';

/**
 * One saved search of saved-search.html (381:8815): tile, name, "Updated …",
 * one chip per kept filter and the "⋯" menu (delete), then under a line the
 * "Notification on / off" switch and "View Result".
 */
import { useMemo, useState } from 'react';
import Link from 'next/link';
import { Menu } from '@base-ui/react/menu';
import { toast } from 'sonner';
import { Loader2, Search } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { showDesignToast } from '@/components/design-system/design-toast';
import { focusRing } from '@/components/design-system/focus-ring';
import { Modal } from '@/components/design-system/Modal';
import { formatRelativeTime } from '@/components/messaging/relative-time';
import { useDeleteSavedSearchMutation, useSavedSearchAlertsMutation } from '@/lib/queries/search';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { SavedSearch } from '@/lib/api/types';

import { ModalActions } from './ModalActions';
import { apiErrorMessage } from './api-error-message';
import { savedSearchChips, savedSearchHref } from './saved-search-params';
import type { SlugLabels } from './useSlugLabels';

interface Props {
  search: SavedSearch;
  /** Names for the category and location slugs of the chips. */
  labels: SlugLabels;
}

export function SavedSearchCard({ search, labels }: Props) {
  const [confirmOpen, setConfirmOpen] = useState(false);
  const deleteMutation = useDeleteSavedSearchMutation();
  const alerts = useSavedSearchAlertsMutation();

  const href = useMemo(() => savedSearchHref(search.query_params), [search]);
  const chips = useMemo(() => savedSearchChips(search.query_params, labels), [search, labels]);

  const onDelete = () => {
    deleteMutation.mutate(search.id, {
      onSuccess: () => {
        showDesignToast(t('account.saved_searches.delete_success', 'تم حذف البحث المحفوظ'));
        setConfirmOpen(false);
      },
      onError: (err) => toast.error(apiErrorMessage(err)),
    });
  };

  const toggleAlerts = () =>
    alerts.mutate(
      { id: search.id, alertsEnabled: !search.alerts_enabled },
      { onError: (err) => toast.error(apiErrorMessage(err)) },
    );

  return (
    <article className="rounded-qb-xl border border-qb-line bg-qb-surface p-5 font-qb">
      <div className="flex items-start gap-4">
        <span
          aria-hidden="true"
          className="flex size-[72px] shrink-0 items-center justify-center rounded-qb-lg bg-qb-fill text-qb-ink-subtle"
        >
          <Search className="size-7" strokeWidth={1.6} />
        </span>
        <div className="min-w-0 flex-1">
          <div className="flex items-start justify-between gap-3">
            <div className="min-w-0">
              <h2 className="text-qb-body-lg font-semibold tracking-normal break-words text-qb-ink">
                <bdi>{search.name}</bdi>
              </h2>
              <p className="mt-[5px] text-qb-label text-qb-ink-subtle">
                {t('account.saved_searches.updated_ago', { when: formatRelativeTime(search.updated_at ?? search.created_at) })}
              </p>
            </div>
            <Menu.Root>
              <Menu.Trigger
                aria-label={t('account.saved_searches.menu_label', { name: search.name })}
                className={cn(
                  'flex h-7 shrink-0 cursor-pointer items-start rounded-qb-xs px-1 text-[22px] leading-none text-qb-ink-subtle hover:text-qb-brand',
                  focusRing,
                )}
              >
                <span aria-hidden="true">⋯</span>
              </Menu.Trigger>
              <Menu.Portal>
                <Menu.Positioner side="bottom" align="end" sideOffset={6} className="z-50">
                  <Menu.Popup className="min-w-40 rounded-[14px] border border-qb-line bg-qb-surface p-2 font-qb shadow-qb-popover outline-none">
                    <Menu.Item
                      onClick={() => setConfirmOpen(true)}
                      className="flex cursor-pointer items-center rounded-[9px] px-3 py-2 text-qb-caption font-medium text-qb-danger outline-none data-highlighted:bg-qb-danger-soft"
                    >
                      {t('account.saved_searches.delete', 'حذف')}
                    </Menu.Item>
                  </Menu.Popup>
                </Menu.Positioner>
              </Menu.Portal>
            </Menu.Root>
          </div>

          <ul className="mt-3 flex flex-wrap gap-2">
            {chips.length > 0 ? (
              chips.map((chip) => (
                <li key={chip.label} className="rounded-qb-xs bg-qb-fill px-[11px] py-[5px] text-qb-micro text-qb-ink-faint">
                  {chip.label}: {chip.value}
                </li>
              ))
            ) : (
              <li className="rounded-qb-xs bg-qb-fill px-[11px] py-[5px] text-qb-micro text-qb-ink-faint">
                {t('search.title_all', 'كل الإعلانات')}
              </li>
            )}
          </ul>
        </div>
      </div>

      <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-qb-line pt-4">
        <button
          type="button"
          role="switch"
          aria-checked={search.alerts_enabled}
          onClick={toggleAlerts}
          disabled={alerts.isPending}
          className={cn(
            'flex cursor-pointer items-center gap-2 rounded-qb-sm px-3 py-[7px] text-qb-caption font-medium disabled:cursor-progress',
            search.alerts_enabled ? 'bg-qb-acct-alert-on-soft text-qb-success' : 'bg-qb-fill text-qb-ink-subtle',
            focusRing,
          )}
        >
          <span aria-hidden="true" className="relative h-[17px] w-[30px] shrink-0 rounded-[9px] bg-current">
            <span
              className={cn(
                'absolute top-0.5 size-[13px] rounded-full bg-qb-surface transition-[inset-inline-start] duration-200 motion-reduce:transition-none',
                search.alerts_enabled ? 'start-[15px]' : 'start-0.5',
              )}
            />
          </span>
          {t(search.alerts_enabled ? 'account.saved_searches.alerts_on' : 'account.saved_searches.alerts_off')}
        </button>
        <Link
          href={href}
          className={cn(
            'rounded-qb-md bg-qb-brand px-6 py-2.5 text-qb-caption font-semibold text-qb-on-brand hover:bg-qb-brand-hover',
            focusRing,
          )}
        >
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
        <ModalActions className="mt-2">
          <Button size="sm" disabled={deleteMutation.isPending} onClick={onDelete}>
            {deleteMutation.isPending ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
            {t('account.saved_searches.delete', 'حذف')}
          </Button>
          <Button variant="muted" size="sm" disabled={deleteMutation.isPending} onClick={() => setConfirmOpen(false)}>
            {t('search.save_search.cancel', 'إلغاء')}
          </Button>
        </ModalActions>
      </Modal>
    </article>
  );
}
