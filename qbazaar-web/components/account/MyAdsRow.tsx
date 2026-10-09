'use client';

/**
 * One row of My Ads (518:20536): photo, title + status chip, category, the
 * visitors / likes / publish-date line and the row actions: edit ("Complete"
 * on drafts), "Reserve" on live ads (orange while reserved), mark sold, renew
 * and delete, as the design's inline buttons.
 */
import { useState } from 'react';
import Link from 'next/link';
import { Bookmark, Clock, Eye, Heart, Loader2, Pencil, RefreshCw, Tag, Trash2 } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/design-system/Button';
import { focusRing } from '@/components/design-system/focus-ring';
import { Modal } from '@/components/design-system/Modal';
import {
  useDeleteAdMutation,
  useMarkSoldMutation,
  useRenewAdMutation,
  useReserveAdMutation,
} from '@/lib/queries/ads';
import { formatAdPrice as formatListingPrice } from '@/lib/ads/format';
import { getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';
import { cn } from '@/lib/utils';
import type { AdSummary } from '@/lib/api/types';

import { AdPhoto } from './AdPhoto';
import { AdStatusBadge } from './AdStatusBadge';
import { ModalActions } from './ModalActions';
import { formatLongDate } from './format';

interface Props {
  ad: AdSummary;
  /** Name of the ad's category in the active language. */
  category: string;
}

/** Grey row buttons of the design ("Complete", "Reserve"): Montserrat 500 14 px on #f7f7f7. */
const rowButton =
  'inline-flex cursor-pointer items-center gap-2 rounded-qb-md bg-qb-acct-tile px-[18px] py-3 font-qb-label text-qb-caption font-medium whitespace-nowrap text-qb-acct-meta hover:bg-qb-fill-strong disabled:cursor-progress disabled:opacity-60 [&_svg]:size-4';

export function MyAdsRow({ ad, category }: Props) {
  const [confirmDelete, setConfirmDelete] = useState(false);
  const deleteMutation = useDeleteAdMutation();
  const markSoldMutation = useMarkSoldMutation();
  const renewMutation = useRenewAdMutation();
  const reserveMutation = useReserveAdMutation();
  const reserved = Boolean(ad.is_reserved);

  const onDelete = async () => {
    try {
      await deleteMutation.mutateAsync(ad.id);
      toast.success(t('ads.actions.delete_success', 'تم حذف الإعلان'));
    } catch (err) {
      toast.error(
        (err as { message?: string })?.message ??
          t('ads.errors.delete_failed', 'تعذّر حذف الإعلان'),
      );
    } finally {
      setConfirmDelete(false);
    }
  };

  const onMarkSold = async () => {
    try {
      await markSoldMutation.mutateAsync(ad.id);
      toast.success(t('ads.actions.mark_sold_success', 'تم تعليم الإعلان كمباع'));
    } catch (err) {
      toast.error((err as { message?: string })?.message ?? t('common.error'));
    }
  };

  const onReserve = async () => {
    try {
      await reserveMutation.mutateAsync({ id: ad.id, reserved: !reserved });
      toast.success(t(reserved ? 'account.my_ads.unreserved_success' : 'account.my_ads.reserved_success'));
    } catch (err) {
      toast.error((err as { message?: string })?.message ?? t('common.error'));
    }
  };

  const onRenew = async () => {
    try {
      await renewMutation.mutateAsync(ad.id);
      toast.success(t('ads.actions.renew_success', 'تم تجديد الإعلان'));
    } catch (err) {
      toast.error((err as { message?: string })?.message ?? t('common.error'));
    }
  };

  const title = ad.title.trim() || t('account.my_ads.untitled');
  const isDraft = ad.status === 'draft';
  const published = formatLongDate(ad.published_at);

  return (
    <article className="flex flex-col gap-4 rounded-qb-2xl bg-qb-surface p-4 font-qb shadow-qb-acct-card qb-tablet:flex-row qb-tablet:flex-wrap qb-tablet:items-center qb-tablet:gap-5">
      <AdPhoto
        image={ad.primary_image}
        sizes="(min-width: 601px) 150px, 100vw"
        dashedWhenEmpty
        className="h-[150px] w-full rounded-qb-xl qb-tablet:h-[120px] qb-tablet:w-[150px]"
      >
        {ad.primary_image && ad.price != null ? (
          // The photo chip carries the amount only, as "QAR 75" in my-ads.html; the price type stays on the ad page.
          <span className="absolute start-2.5 top-2.5 rounded-qb-sm bg-qb-brand px-3 py-1 text-qb-label font-medium whitespace-nowrap text-qb-on-brand">
            {formatListingPrice(ad, getLocale())}
          </span>
        ) : null}
      </AdPhoto>

      <div className="min-w-0 qb-tablet:min-w-[220px] qb-tablet:flex-[1_1_260px]">
        <div className="flex flex-wrap items-center gap-3">
          <h3 className="min-w-0 text-qb-h3 font-semibold tracking-normal text-qb-ink">
            <Link href={`/ads/${ad.id}`} className={cn('rounded-qb-xs hover:text-qb-brand', focusRing)}>
              <bdi>{title}</bdi>
            </Link>
          </h3>
          <AdStatusBadge status={ad.status} reserved={reserved} />
        </div>
        <p className="mt-2 mb-3.5 text-qb-body-sm text-qb-ink-subtle">{category}</p>
        {isDraft ? null : (
          <ul className="flex flex-col gap-2 text-qb-caption text-qb-acct-meta qb-tablet:flex-row qb-tablet:flex-wrap qb-tablet:gap-x-[22px]">
            <li className="flex items-center gap-1.5">
              <Eye aria-hidden="true" className="size-4" strokeWidth={1.6} />
              {tPlural('account.my_ads.visitors', ad.views_count)}
            </li>
            <li className="flex items-center gap-1.5">
              <Heart aria-hidden="true" className="size-4" strokeWidth={1.6} />
              {tPlural('account.my_ads.likes', ad.favorites_count)}
            </li>
            {published ? (
              <li className="flex items-center gap-1.5">
                <Clock aria-hidden="true" className="size-4" strokeWidth={1.6} />
                {t('account.my_ads.published_on', { date: published })}
              </li>
            ) : null}
          </ul>
        )}
      </div>

      <div className="flex flex-wrap items-center justify-end gap-3.5">
        <Link href={`/account/ads/${ad.id}/edit`} className={cn(rowButton, focusRing)}>
          <Pencil aria-hidden="true" />
          {isDraft ? t('account.my_ads.complete') : t('ads.actions.edit', 'تعديل')}
        </Link>
        {ad.status === 'active' ? (
          <button
            type="button"
            aria-pressed={reserved}
            className={cn(rowButton, reserved && 'bg-qb-brand-soft text-qb-brand hover:bg-qb-brand-soft', focusRing)}
            disabled={reserveMutation.isPending}
            onClick={() => void onReserve()}
          >
            {reserveMutation.isPending ? <Loader2 className="animate-spin" aria-hidden="true" /> : <Bookmark aria-hidden="true" />}
            {t('account.my_ads.reserve')}
          </button>
        ) : null}
        {ad.status === 'active' ? (
          <button
            type="button"
            className={cn(rowButton, focusRing)}
            disabled={markSoldMutation.isPending}
            onClick={() => void onMarkSold()}
          >
            {markSoldMutation.isPending ? <Loader2 className="animate-spin" aria-hidden="true" /> : <Tag aria-hidden="true" />}
            {t('ads.actions.mark_sold', 'تم البيع')}
          </button>
        ) : null}
        {ad.status === 'expired' ? (
          <button
            type="button"
            className={cn(rowButton, focusRing)}
            disabled={renewMutation.isPending}
            onClick={() => void onRenew()}
          >
            {renewMutation.isPending ? <Loader2 className="animate-spin" aria-hidden="true" /> : <RefreshCw aria-hidden="true" />}
            {t('ads.actions.renew', 'تجديد')}
          </button>
        ) : null}
        <button
          type="button"
          className={cn(
            'flex size-11 cursor-pointer items-center justify-center rounded-qb-md border border-qb-line bg-qb-surface text-qb-acct-danger hover:bg-qb-danger-soft',
            focusRing,
          )}
          aria-label={t('account.my_ads.delete_label', { title })}
          onClick={() => setConfirmDelete(true)}
        >
          <Trash2 aria-hidden="true" className="size-[18px]" strokeWidth={1.6} />
        </button>
      </div>

      <Modal
        open={confirmDelete}
        onOpenChange={(open) => {
          if (!open && !deleteMutation.isPending) setConfirmDelete(false);
        }}
        title={t('ads.actions.delete_confirm_title', 'تأكيد الحذف')}
        description={t(
          'ads.actions.delete_confirm_body',
          'سيتم حذف الإعلان نهائياً. لا يمكن التراجع عن هذا الإجراء.',
        )}
      >
        <ModalActions className="mt-2">
          <Button size="sm" disabled={deleteMutation.isPending} onClick={() => void onDelete()}>
            {deleteMutation.isPending ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
            {t('common.delete', 'حذف')}
          </Button>
          <Button variant="muted" size="sm" disabled={deleteMutation.isPending} onClick={() => setConfirmDelete(false)}>
            {t('common.cancel', 'إلغاء')}
          </Button>
        </ModalActions>
      </Modal>
    </article>
  );
}
