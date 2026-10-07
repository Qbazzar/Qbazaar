'use client';

/**
 * One row of My Ads (518:20536): photo, title + status chip, category, the
 * visitors / likes / publish-date line and the row actions. Actions are the
 * same as before — edit ("Complete" on drafts), mark sold, renew, delete —
 * shown as the design's inline buttons instead of a menu.
 */
import { useState } from 'react';
import Link from 'next/link';
import { Clock, Eye, Heart, Loader2, RefreshCw, Tag, Trash2, WandSparkles } from 'lucide-react';
import { toast } from 'sonner';

import { Badge } from '@/components/design-system/Badge';
import { Button, buttonVariants } from '@/components/design-system/Button';
import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { Modal } from '@/components/design-system/Modal';
import {
  useDeleteAdMutation,
  useMarkSoldMutation,
  useRenewAdMutation,
} from '@/lib/queries/ads';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';
import { cn } from '@/lib/utils';
import type { AdSummary } from '@/lib/api/types';

import { AdPhoto } from './AdPhoto';
import { AdStatusBadge } from './AdStatusBadge';
import { ModalActions } from './ModalActions';
import { formatAdPrice, formatLongDate } from './format';

interface Props {
  ad: AdSummary;
  /** Name of the ad's category in the active language. */
  category: string;
}

/** Montserrat 14 px row buttons of the design ("Complete", "Reserve"). */
const rowButton = 'h-[34px] gap-1.5 rounded-qb-sm px-3 font-qb-label text-qb-caption font-medium [&_svg]:size-4';

export function MyAdsRow({ ad, category }: Props) {
  const [confirmDelete, setConfirmDelete] = useState(false);
  const deleteMutation = useDeleteAdMutation();
  const markSoldMutation = useMarkSoldMutation();
  const renewMutation = useRenewAdMutation();

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
    <article className="flex flex-col gap-4 rounded-qb-2xl border border-qb-line bg-qb-surface p-[17px] font-qb shadow-qb-card qb-tablet:flex-row qb-tablet:items-center qb-tablet:gap-6">
      <AdPhoto
        image={ad.primary_image}
        sizes="(min-width: 601px) 199px, 100vw"
        className="h-[148px] w-full rounded-qb-2xl qb-tablet:w-[199px]"
      >
        {isDraft ? null : (
          <Badge tone="solid" className="absolute start-2.5 top-2.5 rounded-qb-xs px-1.5 text-qb-caption">
            {formatAdPrice(ad)}
          </Badge>
        )}
      </AdPhoto>

      <div className="flex min-w-0 flex-1 flex-col gap-4 qb-desktop:flex-row qb-desktop:items-center qb-desktop:gap-6">
        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
            <h3 className="min-w-0 text-qb-h5 font-semibold tracking-normal text-qb-ink-title qb-desktop:text-qb-h2">
              <Link href={`/ads/${ad.id}`} className={cn('rounded-qb-xs hover:text-qb-brand', focusRing)}>
                <bdi>{title}</bdi>
              </Link>
            </h3>
            <AdStatusBadge status={ad.status} />
          </div>
          <p className="mt-2 text-qb-caption text-qb-ink-subtle qb-tablet:mt-3 qb-desktop:text-qb-body">{category}</p>
          {isDraft ? null : (
            <ul className="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-qb-caption text-qb-ink-subtle qb-desktop:text-qb-body">
              <li className="flex items-center gap-1.5">
                <Icon icon={Eye} />
                {tPlural('account.my_ads.visitors', ad.views_count)}
              </li>
              <li className="flex items-center gap-1.5">
                <Icon icon={Heart} />
                {tPlural('account.my_ads.likes', ad.favorites_count)}
              </li>
              {published ? (
                <li className="flex items-center gap-1.5">
                  <Icon icon={Clock} />
                  {t('account.my_ads.published_on', { date: published })}
                </li>
              ) : null}
            </ul>
          )}
        </div>

        <div className="flex flex-wrap items-center justify-end gap-2.5">
          <Link
            href={`/account/ads/${ad.id}/edit`}
            className={cn(buttonVariants({ variant: 'muted', size: 'sm' }), rowButton)}
          >
            <WandSparkles aria-hidden="true" />
            {isDraft ? t('account.my_ads.complete') : t('ads.actions.edit', 'تعديل')}
          </Link>
          {ad.status === 'active' ? (
            <Button
              variant="outline"
              size="sm"
              className={rowButton}
              disabled={markSoldMutation.isPending}
              onClick={() => void onMarkSold()}
            >
              {markSoldMutation.isPending ? <Loader2 className="animate-spin" aria-hidden="true" /> : <Tag aria-hidden="true" />}
              {t('ads.actions.mark_sold', 'تم البيع')}
            </Button>
          ) : null}
          {ad.status === 'expired' ? (
            <Button
              variant="outline"
              size="sm"
              className={rowButton}
              disabled={renewMutation.isPending}
              onClick={() => void onRenew()}
            >
              {renewMutation.isPending ? <Loader2 className="animate-spin" aria-hidden="true" /> : <RefreshCw aria-hidden="true" />}
              {t('ads.actions.renew', 'تجديد')}
            </Button>
          ) : null}
          <Button
            variant="danger"
            size="icon"
            className="h-[34px] w-11 rounded-qb-sm border-transparent [&_svg]:size-[18px]"
            aria-label={t('account.my_ads.delete_label', { title })}
            onClick={() => setConfirmDelete(true)}
          >
            <Trash2 aria-hidden="true" />
          </Button>
        </div>
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
