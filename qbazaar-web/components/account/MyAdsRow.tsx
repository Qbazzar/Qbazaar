'use client';

/**
 * One row in the "My Ads" list.
 *
 * Wraps `AdCard` with status pill + actions dropdown (Edit / Mark sold /
 * Renew / Delete). Actions kick off the matching mutation; success toasts
 * are surfaced here so the page can stay declarative.
 */
import { useState } from 'react';
import Link from 'next/link';
import { MoreHorizontal } from 'lucide-react';
import { toast } from 'sonner';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';
import { AdCard } from '@/components/ads/AdCard';
import { AdStatusPill } from '@/components/ads/AdStatusPill';
import {
  useDeleteAdMutation,
  useMarkSoldMutation,
  useRenewAdMutation,
} from '@/lib/queries/ads';
import { t } from '@/lib/i18n/messages';
import type { AdSummary } from '@/lib/api/types';

interface Props {
  ad: AdSummary;
}

export function MyAdsRow({ ad }: Props) {
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

  return (
    <div className="relative">
      <AdCard
        ad={ad}
        footer={
          <div className="flex items-center justify-between pt-1">
            <AdStatusPill status={ad.status} />
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button
                  type="button"
                  size="icon-sm"
                  variant="ghost"
                  aria-label={t('common.more', 'المزيد')}
                  onClick={(e) => {
                    // Prevent the wrapping AdCard <Link> from also firing.
                    e.preventDefault();
                    e.stopPropagation();
                  }}
                >
                  <MoreHorizontal className="size-4" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                <DropdownMenuItem asChild>
                  <Link href={`/ads/${ad.id}`}>
                    {t('ads.actions.view', 'عرض')}
                  </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                  <Link href={`/account/ads/${ad.id}/edit`}>
                    {t('ads.actions.edit', 'تعديل')}
                  </Link>
                </DropdownMenuItem>
                {ad.status === 'active' ? (
                  <DropdownMenuItem onClick={() => void onMarkSold()}>
                    {t('ads.actions.mark_sold', 'تم البيع')}
                  </DropdownMenuItem>
                ) : null}
                {ad.status === 'expired' ? (
                  <DropdownMenuItem onClick={() => void onRenew()}>
                    {t('ads.actions.renew', 'تجديد')}
                  </DropdownMenuItem>
                ) : null}
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  className="text-destructive focus:text-destructive"
                  onClick={() => setConfirmDelete(true)}
                >
                  {t('ads.actions.delete', 'حذف')}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        }
      />

      {confirmDelete ? (
        <div
          role="dialog"
          aria-modal="true"
          className="fixed inset-0 z-50 flex items-center justify-center bg-ink-900/60 p-4"
          onClick={() => setConfirmDelete(false)}
        >
          <div
            className="w-full max-w-sm rounded-2xl bg-card p-6 shadow-xl"
            onClick={(e) => e.stopPropagation()}
          >
            <h3 className="font-display text-xl text-ink-900">
              {t('ads.actions.delete_confirm_title', 'تأكيد الحذف')}
            </h3>
            <p className="text-ink-700 mt-2 text-sm">
              {t(
                'ads.actions.delete_confirm_body',
                'سيتم حذف الإعلان نهائياً. لا يمكن التراجع عن هذا الإجراء.',
              )}
            </p>
            <div className="mt-5 flex justify-end gap-2">
              <Button
                type="button"
                variant="outline"
                onClick={() => setConfirmDelete(false)}
              >
                {t('common.cancel', 'إلغاء')}
              </Button>
              <Button
                type="button"
                variant="destructive"
                onClick={() => void onDelete()}
                disabled={deleteMutation.isPending}
              >
                {t('common.delete', 'حذف')}
              </Button>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  );
}
