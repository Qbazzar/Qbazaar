'use client';

/**
 * Inline offer card rendered for `Message.type === 'offer'`, in the layout of
 * the chat's purchase-request card (667:30685).
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
import { Check, Handshake, Loader2, X } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/design-system/Button';
import { Modal } from '@/components/design-system/Modal';
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
    <div className={cn('flex w-full', isMine ? 'justify-end' : 'justify-start')}>
      <div
        role="group"
        aria-label={t('messaging.offer.card_title')}
        className="flex w-full max-w-[534px] flex-col gap-4 rounded-qb-md border border-qb-line bg-qb-surface p-4 font-qb shadow-qb-soft qb-tablet:px-[22px] qb-tablet:py-5"
      >
        <div className="flex items-center justify-between gap-3">
          <p className="flex items-center gap-2 text-qb-body font-medium text-qb-ink">
            <Handshake className="size-5 shrink-0 text-qb-brand" aria-hidden="true" />
            {t('messaging.offer.card_title')}
          </p>
          <OfferStatusBadge status={offer.status} />
        </div>

        <p className="text-qb-h3 leading-none font-semibold text-qb-ink" dir="ltr">
          {formatCurrency(offer.amount)}{' '}
          <span className="text-qb-caption font-medium text-qb-ink-secondary">{offer.currency}</span>
        </p>

        {offer.note ? (
          <div className="relative border border-qb-line px-3 py-3 before:absolute before:inset-y-0 before:start-0 before:w-0.5 before:rounded-qb-pill before:bg-qb-brand">
            <p className="text-qb-tiny text-qb-ink-subtle">{t('messaging.offer.note_label', 'ملاحظة (اختياري)')}</p>
            <p dir="auto" className="mt-2 text-qb-label leading-relaxed whitespace-pre-wrap break-words text-qb-ink">
              {offer.note}
            </p>
          </div>
        ) : null}

        {expiryHint ? <p className="text-qb-label font-semibold text-qb-danger">{expiryHint}</p> : null}

        {/* Action row — buyer / seller / terminal */}
        {isPending && isSeller ? (
          <div className="grid grid-cols-2 gap-2">
            <Button size="sm" onClick={onAccept} disabled={busy}>
              {acceptMutation.isPending ? (
                <Loader2 className="animate-spin" aria-hidden="true" />
              ) : (
                <Check aria-hidden="true" />
              )}
              {t('messaging.offer.accept', 'قبول')}
            </Button>
            <Button variant="muted" size="sm" onClick={onReject} disabled={busy}>
              {rejectMutation.isPending ? (
                <Loader2 className="animate-spin" aria-hidden="true" />
              ) : (
                <X aria-hidden="true" />
              )}
              {t('messaging.offer.reject', 'رفض')}
            </Button>
          </div>
        ) : null}

        {isPending && isBuyer ? (
          <Button variant="muted" size="sm" onClick={() => setConfirmOpen(true)} disabled={busy} className="self-start">
            {t('messaging.offer.withdraw', 'سحب العرض')}
          </Button>
        ) : null}

        {terminalLabel ? <p className="text-qb-label text-qb-ink-subtle">{terminalLabel}</p> : null}
      </div>

      <Modal
        open={confirmOpen}
        onOpenChange={(open) => {
          if (!open && !withdrawMutation.isPending) setConfirmOpen(false);
        }}
        title={t('messaging.offer.withdraw_confirm.title', 'سحب العرض؟')}
        description={t(
          'messaging.offer.withdraw_confirm.body',
          'لن يتمكن البائع من قبول هذا العرض بعد سحبه.',
        )}
      >
        <div className="mt-2 grid grid-cols-2 gap-3 qb-tablet:gap-5">
          <Button size="sm" onClick={onWithdraw} disabled={withdrawMutation.isPending}>
            {withdrawMutation.isPending ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
            {t('messaging.offer.withdraw_confirm.confirm', 'تأكيد السحب')}
          </Button>
          <Button variant="muted" size="sm" onClick={() => setConfirmOpen(false)} disabled={withdrawMutation.isPending}>
            {t('messaging.offer.cancel', 'إلغاء')}
          </Button>
        </div>
      </Modal>
    </div>
  );
}
