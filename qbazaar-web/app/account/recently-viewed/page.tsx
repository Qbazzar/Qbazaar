'use client';

/**
 * FE-7.x — Recently viewed index. No frame: the wishlist rows of 376:8322
 * with a "Clear" ghost button in place of the remove hearts.
 *
 * Auth-gated. Lists every ad the user has viewed (paginated). "Clear all"
 * opens a confirmation dialog and fires the clear mutation; on success the
 * query invalidates and the page falls back to the empty state.
 */
import { useState } from 'react';
import Link from 'next/link';
import { Clock, Loader2, Trash2 } from 'lucide-react';
import { toast } from 'sonner';

import { Button, buttonVariants } from '@/components/design-system/Button';
import { EmptyState } from '@/components/design-system/EmptyState';
import { Icon } from '@/components/design-system/Icon';
import { Modal } from '@/components/design-system/Modal';
import { AccountPage } from '@/components/account/AccountPage';
import { ModalActions } from '@/components/account/ModalActions';
import { PanelState } from '@/components/account/PanelState';
import { Pager } from '@/components/account/Pager';
import { SavedAdRow } from '@/components/account/SavedAdRow';
import { useSlugLabels } from '@/components/account/useSlugLabels';
import {
  useClearRecentlyViewedMutation,
  useRecentlyViewedQuery,
} from '@/lib/queries/recently-viewed';
import { t } from '@/lib/i18n/messages';
import { ApiClientError } from '@/lib/api/auth';

const PER_PAGE = 24;

export default function RecentlyViewedPage() {
  const [page, setPage] = useState(1);
  const [confirmOpen, setConfirmOpen] = useState(false);
  const { data, isLoading, isError, error } = useRecentlyViewedQuery({
    page,
    per_page: PER_PAGE,
  });
  const clearMutation = useClearRecentlyViewedMutation();
  const labels = useSlugLabels();

  const lastPage = data?.meta.last_page ?? 1;
  const hasItems = (data?.data.length ?? 0) > 0;

  const handleClear = () => {
    clearMutation.mutate(undefined, {
      onSuccess: () => {
        toast.success(t('recently_viewed.cleared', 'تم مسح السجل'));
        setConfirmOpen(false);
        setPage(1);
      },
      onError: (err) => {
        toast.error(
          err instanceof ApiClientError ? err.message : t('common.error'),
        );
      },
    });
  };

  return (
    <AccountPage
      title={t('recently_viewed.title', 'آخر ما شاهدت')}
      titleClassName="qb-desktop:text-[44px]"
      actions={
        hasItems ? (
          <Button variant="ghost" size="sm" onClick={() => setConfirmOpen(true)}>
            <Trash2 aria-hidden="true" />
            {t('recently_viewed.clear', 'مسح الكل')}
          </Button>
        ) : null
      }
    >
      {isLoading ? (
        <PanelState loading />
      ) : isError ? (
        <PanelState
          loading={false}
          message={error instanceof ApiClientError ? error.message : t('common.error', 'حدث خطأ، حاول مرة أخرى')}
        />
      ) : !hasItems || !data ? (
        <EmptyState
          icon={<Icon icon={Clock} size="lg" />}
          title={t('recently_viewed.empty', 'لا يوجد سجل مشاهدة بعد')}
          description={t('recently_viewed.empty_body')}
          action={
            <Link href="/ads" className={buttonVariants({ size: 'sm' })}>
              {t('favorites.empty.cta', 'تصفّح الإعلانات')}
            </Link>
          }
          className="rounded-qb-2xl border border-qb-line bg-qb-surface py-20 shadow-qb-card"
        />
      ) : (
        <>
          <ul className="flex flex-col gap-4 qb-tablet:gap-6">
            {data.data.map((ad) => (
              <li key={ad.id}>
                <SavedAdRow ad={ad} labels={labels} at={ad.viewed_at} />
              </li>
            ))}
          </ul>
          <Pager page={page} lastPage={lastPage} onChange={setPage} />
        </>
      )}

      <Modal
        open={confirmOpen}
        onOpenChange={(open) => {
          if (!open && !clearMutation.isPending) setConfirmOpen(false);
        }}
        title={t('recently_viewed.clear_confirm.title', 'مسح السجل؟')}
        description={t('recently_viewed.clear_confirm.body')}
      >
        <ModalActions className="mt-2">
          <Button size="sm" disabled={clearMutation.isPending} onClick={handleClear}>
            {clearMutation.isPending ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
            {t('recently_viewed.clear_confirm.confirm', 'مسح')}
          </Button>
          <Button
            variant="muted"
            size="sm"
            disabled={clearMutation.isPending}
            onClick={() => setConfirmOpen(false)}
          >
            {t('recently_viewed.clear_confirm.cancel', 'إلغاء')}
          </Button>
        </ModalActions>
      </Modal>
    </AccountPage>
  );
}
