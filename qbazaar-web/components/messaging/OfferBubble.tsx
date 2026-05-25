'use client';

/**
 * Inline offer card rendered for `Message.type === 'offer'`.
 *
 * Behaviour is driven by `offer.viewer_role` + `offer.status`:
 *   - buyer  + pending → "withdraw" button (with confirm dialog)
 *   - seller + pending → "accept" (coral fill) + "reject" (outline)
 *   - terminal status  → muted timestamp explaining when it transitioned
 *
 * When the offer is `pending` and less than 24h from expiry, a red badge
 * surfaces a countdown so users know to act quickly.
 */
import { useMemo, useState } from 'react';
import { CheckIcon, HandshakeIcon, Loader2Icon, XIcon } from 'lucide-react';
import { toast } from 'sonner';
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import {
  useAcceptOfferMutation,
  useRejectOfferMutation,
  useWithdrawOfferMutation,
} from '@/lib/queries/offers';
import { OfferStatusBadge } from './OfferStatusBadge';
import { formatRelativeTime } from './relative-time';
import type { Offer, OfferStatus } from '@/lib/api/types';

interface Props {
  offer: Offer;
  isMine: boolean;
}

const STATUS_TERMINAL_KEY: Partial<Record<OfferStatus, string>> = {
  accepted: 'messaging.offer.status.accepted',
  rejected: 'messaging.offer.status.rejected',
  withdrawn: 'messaging.offer.status.withdrawn',
  expired: 'messaging.offer.status.expired',
};

const STATUS_TERMINAL_FALLBACK: Partial<Record<OfferStatus, string>> = {
  accepted: 'تم القبول',
  rejected: 'مرفوض',
  withdrawn: 'تم السحب',
  expired: 'منتهي',
};

const HOUR_MS = 60 * 60 * 1000;
const DAY_MS = 24 * HOUR_MS;

function formatCurrency(amount: number): string {
  // Render with grouping but no fractional part when integer — matches the
  // pricing pill convention used everywhere else in the app.
  const hasFraction = !Number.isInteger(amount);
  return new Intl.NumberFormat(undefined, {
    maximumFractionDigits: hasFraction ? 2 : 0,
    minimumFractionDigits: hasFraction ? 2 : 0,
  }).format(amount);
}

function terminalTimestamp(offer: Offer): string | null {
  switch (offer.status) {
    case 'accepted':
      return offer.accepted_at;
    case 'rejected':
      return offer.rejected_at;
    case 'withdrawn':
      return offer.withdrawn_at;
    case 'expired':
      return offer.expires_at;
    default:
      return null;
  }
}

export function OfferBubble({ offer, isMine }: Props) {
  const [confirmOpen, setConfirmOpen] = useState(false);

  const acceptMutation = useAcceptOfferMutation();
  const rejectMutation = useRejectOfferMutation();
  const withdrawMutation = useWithdrawOfferMutation();

  const isPending = offer.status === 'pending';
  const isBuyer = offer.viewer_role === 'buyer';
  const isSeller = offer.viewer_role === 'seller';

  // Surface a countdown when the offer expires inside the next 24 hours so
  // both parties know to act quickly. Computed once per render — the
  // surrounding queries/Echo events will re-render us on status change.
  const expiryHint = useMemo(() => {
    if (!isPending) return null;
    const expiresAt = new Date(offer.expires_at).getTime();
    if (Number.isNaN(expiresAt)) return null;
    const remaining = expiresAt - Date.now();
    if (remaining <= 0 || remaining >= DAY_MS) return null;
    const hours = Math.max(1, Math.ceil(remaining / HOUR_MS));
    return t('messaging.offer.expires_in_hours', { hours }, `تنتهي خلال ${hours} ساعات`);
  }, [isPending, offer.expires_at]);

  const handleError = (err: unknown) => {
    const fallback = t('common.error', 'حدث خطأ، حاول مرة أخرى');
    const code = (err as { code?: string } | null)?.code;
    const messageKey = (err as { messageKey?: string } | null)?.messageKey;

    if (code === 'OFFER_NOT_PENDING') {
      toast.error(
        t('messaging.offer.errors.not_pending', 'لم يعد بإمكان تعديل هذا العرض'),
      );
      return;
    }
    if (code === 'OFFER_FORBIDDEN') {
      toast.error(
        t('messaging.offer.errors.forbidden', 'لا تملك صلاحية تنفيذ هذا الإجراء'),
      );
      return;
    }
    if (code === 'OFFER_NOT_FOUND') {
      toast.error(t('messaging.offer.errors.not_found', 'لم نعثر على العرض'));
      return;
    }
    toast.error(translateMaybeKey(messageKey) || fallback);
  };

  const onAccept = async () => {
    try {
      await acceptMutation.mutateAsync(offer.id);
    } catch (err) {
      handleError(err);
    }
  };
  const onReject = async () => {
    try {
      await rejectMutation.mutateAsync(offer.id);
    } catch (err) {
      handleError(err);
    }
  };
  const onWithdraw = async () => {
    try {
      await withdrawMutation.mutateAsync(offer.id);
      setConfirmOpen(false);
    } catch (err) {
      handleError(err);
    }
  };

  const busy =
    acceptMutation.isPending ||
    rejectMutation.isPending ||
    withdrawMutation.isPending;

  const terminalAt = terminalTimestamp(offer);
  const terminalLabel =
    !isPending && terminalAt
      ? t(
          'messaging.offer.terminal_message',
          {
            status: t(
              STATUS_TERMINAL_KEY[offer.status] ?? `messaging.offer.status.${offer.status}`,
              STATUS_TERMINAL_FALLBACK[offer.status] ?? offer.status,
            ),
            when: formatRelativeTime(terminalAt),
          },
          `${STATUS_TERMINAL_FALLBACK[offer.status] ?? offer.status} ${formatRelativeTime(terminalAt)}`,
        )
      : null;

  return (
    <div
      className={cn(
        'flex w-full',
        isMine ? 'justify-end' : 'justify-start',
      )}
    >
      <div
        className={cn(
          'flex w-full max-w-[88%] flex-col gap-3 rounded-2xl border p-4',
          'border-coral/40 bg-cream-50',
          'shadow-[inset_4px_0_0_0_var(--color-sage,_#7BB591)] rtl:shadow-[inset_-4px_0_0_0_var(--color-sage,_#7BB591)]',
        )}
        role="group"
        aria-label={t('messaging.offer.make', 'اعرض سعر')}
      >
        <div className="flex items-center justify-between gap-3">
          <div className="flex min-w-0 items-center gap-2">
            <HandshakeIcon className="text-coral size-5 shrink-0" aria-hidden />
            <div className="flex flex-col">
              <span className="text-ink-900 font-display text-2xl font-bold leading-tight">
                {formatCurrency(offer.amount)}{' '}
                <span className="text-ink-700 text-sm font-medium">
                  {offer.currency}
                </span>
              </span>
            </div>
          </div>
          <OfferStatusBadge status={offer.status} />
        </div>

        {offer.note ? (
          <p className="text-ink-700 border-coral/20 border-s-2 ps-3 text-sm leading-relaxed whitespace-pre-wrap break-words">
            {offer.note}
          </p>
        ) : null}

        {expiryHint ? (
          <p className="text-destructive text-xs font-bold">{expiryHint}</p>
        ) : null}

        {/* Action row — buyer / seller / terminal */}
        {isPending && isSeller ? (
          <div className="flex flex-wrap items-center gap-2">
            <Button
              type="button"
              size="sm"
              onClick={onAccept}
              disabled={busy}
              className="bg-coral hover:bg-coral/90 rounded-full text-white"
            >
              {acceptMutation.isPending ? (
                <Loader2Icon className="size-4 animate-spin" aria-hidden />
              ) : (
                <CheckIcon className="size-4" aria-hidden />
              )}
              {t('messaging.offer.accept', 'قبول')}
            </Button>
            <Button
              type="button"
              size="sm"
              variant="outline"
              onClick={onReject}
              disabled={busy}
              className="border-ink-300 rounded-full"
            >
              {rejectMutation.isPending ? (
                <Loader2Icon className="size-4 animate-spin" aria-hidden />
              ) : (
                <XIcon className="size-4" aria-hidden />
              )}
              {t('messaging.offer.reject', 'رفض')}
            </Button>
          </div>
        ) : null}

        {isPending && isBuyer ? (
          <div className="flex flex-wrap items-center gap-2">
            <Button
              type="button"
              size="sm"
              variant="outline"
              onClick={() => setConfirmOpen(true)}
              disabled={busy}
              className="border-ink-300 rounded-full"
            >
              {t('messaging.offer.withdraw', 'سحب العرض')}
            </Button>
          </div>
        ) : null}

        {terminalLabel ? (
          <p className="text-ink-500 text-xs">{terminalLabel}</p>
        ) : null}
      </div>

      <Dialog open={confirmOpen} onOpenChange={setConfirmOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>
              {t(
                'messaging.offer.withdraw_confirm.title',
                'سحب العرض؟',
              )}
            </DialogTitle>
            <DialogDescription>
              {t(
                'messaging.offer.withdraw_confirm.body',
                'لن يتمكن البائع من قبول هذا العرض بعد سحبه.',
              )}
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <DialogClose
              render={
                <Button type="button" variant="outline" className="rounded-full">
                  {t('messaging.offer.cancel', 'إلغاء')}
                </Button>
              }
            />
            <Button
              type="button"
              onClick={onWithdraw}
              disabled={withdrawMutation.isPending}
              className="bg-coral hover:bg-coral/90 rounded-full text-white"
            >
              {withdrawMutation.isPending ? (
                <Loader2Icon className="size-4 animate-spin" aria-hidden />
              ) : null}
              {t(
                'messaging.offer.withdraw_confirm.confirm',
                'تأكيد السحب',
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
