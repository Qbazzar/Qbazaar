'use client';

/**
 * Saved searches of saved-search.html (381:8815, empty 381:8657). Auth-gated
 * by the wrapping `app/account/layout.tsx`. "Clear all" in the header removes
 * every saved search after a confirmation; the "Clean up" banner under the
 * list removes the inactive ones, those whose notifications are off.
 */
import { useState } from 'react';
import Link from 'next/link';
import { toast } from 'sonner';
import { CircleAlert, Loader2, Search } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { focusRing } from '@/components/design-system/focus-ring';
import { Modal } from '@/components/design-system/Modal';
import { AccountEmptyState } from '@/components/account/AccountEmptyState';
import { AccountPage, compactTitleClass } from '@/components/account/AccountPage';
import { ModalActions } from '@/components/account/ModalActions';
import { PanelState } from '@/components/account/PanelState';
import { SavedSearchCard } from '@/components/account/SavedSearchCard';
import { useSlugLabels } from '@/components/account/useSlugLabels';
import { useDeleteSavedSearchesMutation, useSavedSearchesQuery } from '@/lib/queries/search';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';
import { ApiClientError } from '@/lib/api/auth';
import type { SavedSearch } from '@/lib/api/types';
import { cn } from '@/lib/utils';

const primaryButtonClass = cn(
  'inline-flex cursor-pointer items-center justify-center gap-2 rounded-qb-md bg-qb-brand px-6 py-2.5 text-qb-caption font-semibold whitespace-nowrap text-qb-on-brand hover:bg-qb-brand-hover disabled:cursor-progress disabled:opacity-60',
  focusRing,
);

export default function SavedSearchesPage() {
  const { data, isLoading, isError, error } = useSavedSearchesQuery();
  const labels = useSlugLabels();
  const [confirmClear, setConfirmClear] = useState(false);
  const hasSearches = Boolean(data && data.length > 0);

  return (
    <AccountPage
      title={t('account.saved_searches.title', 'عمليات البحث المحفوظة')}
      titleClassName={compactTitleClass}
      actions={
        hasSearches ? (
          <button
            type="button"
            onClick={() => setConfirmClear(true)}
            className={cn('cursor-pointer rounded-qb-xs text-qb-caption text-qb-ink-subtle hover:text-qb-brand', focusRing)}
          >
            {t('account.saved_searches.clear_all')}
          </button>
        ) : null
      }
    >
      {isLoading ? (
        <PanelState loading />
      ) : isError ? (
        <PanelState
          loading={false}
          message={
            error instanceof ApiClientError
              ? translateMaybeKey(`search.errors.${error.code.toLowerCase()}`) || error.message
              : t('search.errors.load_failed', 'تعذّر تحميل البيانات')
          }
        />
      ) : !data || data.length === 0 ? (
        <AccountEmptyState
          icon={<Search strokeWidth={1.6} />}
          title={t('account.saved_searches.empty_title', 'لا توجد عمليات بحث محفوظة بعد')}
          description={t('account.saved_searches.empty_body')}
          action={
            <Link
              href="/search"
              className={cn(
                'inline-flex rounded-qb-lg bg-qb-brand px-8 py-3.5 text-qb-body-sm font-semibold text-qb-on-brand hover:bg-qb-brand-hover',
                focusRing,
              )}
            >
              {t('account.saved_searches.browse_ads')}
            </Link>
          }
          className="rounded-[20px]"
        />
      ) : (
        <div className="flex flex-col gap-[18px]">
          <ul className="flex flex-col gap-[18px]">
            {data.map((search) => (
              <li key={search.id}>
                <SavedSearchCard search={search} labels={labels} />
              </li>
            ))}
          </ul>
          <CleanUpBanner searches={data} />
        </div>
      )}

      <ClearAllDialog open={confirmClear} onOpenChange={setConfirmClear} ids={data?.map((search) => search.id) ?? []} />
    </AccountPage>
  );
}

function ClearAllDialog({
  open,
  onOpenChange,
  ids,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  ids: string[];
}) {
  const remove = useDeleteSavedSearchesMutation();

  const clear = () =>
    remove.mutate(ids, {
      onSuccess: (failed) => {
        onOpenChange(false);
        if (failed > 0) toast.error(t('search.errors.delete_failed'));
        else toast.success(t('account.saved_searches.cleared'));
      },
    });

  return (
    <Modal
      open={open}
      onOpenChange={(next) => {
        if (!remove.isPending) onOpenChange(next);
      }}
      title={t('account.saved_searches.clear_confirm_title')}
      description={t('account.saved_searches.clear_confirm_body')}
    >
      <ModalActions className="mt-2">
        <Button size="sm" disabled={remove.isPending} onClick={clear}>
          {remove.isPending ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
          {t('account.saved_searches.clear_all')}
        </Button>
        <Button variant="muted" size="sm" disabled={remove.isPending} onClick={() => onOpenChange(false)}>
          {t('search.save_search.cancel', 'إلغاء')}
        </Button>
      </ModalActions>
    </Modal>
  );
}

/** "Clean up inactive items" of saved-search.html: deletes the saved searches whose notifications are off. */
function CleanUpBanner({ searches }: { searches: readonly SavedSearch[] }) {
  const remove = useDeleteSavedSearchesMutation();
  const inactiveIds = searches.filter((search) => !search.alerts_enabled).map((search) => search.id);

  const cleanUp = () => {
    if (inactiveIds.length === 0) {
      toast(t('account.saved_searches.cleanup_none'));
      return;
    }
    remove.mutate(inactiveIds, {
      onSuccess: (failed) => {
        if (failed > 0) toast.error(t('search.errors.delete_failed'));
        else toast.success(tPlural('account.saved_searches.cleaned', inactiveIds.length));
      },
    });
  };

  return (
    <div className="flex flex-wrap items-center justify-between gap-3.5 rounded-qb-xl border border-qb-acct-banner-line bg-qb-acct-banner px-[22px] py-[18px]">
      <p className="flex flex-wrap items-center gap-2.5 text-qb-body-sm text-qb-ink-body">
        <CircleAlert aria-hidden="true" className="size-5 shrink-0 text-qb-brand" strokeWidth={1.7} />
        {t('account.saved_searches.cleanup_title')}
        <span className="text-qb-label text-qb-ink-subtle">— {t('account.saved_searches.cleanup_body')}</span>
      </p>
      <button type="button" onClick={cleanUp} disabled={remove.isPending} className={primaryButtonClass}>
        {remove.isPending ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
        {t('account.saved_searches.cleanup')}
      </button>
    </div>
  );
}
