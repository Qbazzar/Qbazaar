'use client';

import { useState, type ReactNode } from 'react';
import Link from 'next/link';
import { useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';

import { Button, buttonVariants } from '@/components/design-system/Button';
import type { DealCardAd, DealOffer, DealRole } from '@/lib/api/commerce-types';
import { payloadField, useDealEvents } from '@/lib/echo/useDealEvents';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';
import { dealErrorMessage, isHandledGlobally } from '@/lib/orders/errors';
import { formatMoney } from '@/lib/orders/money';
import { OFFER_TONE, type StatusTone } from '@/lib/orders/status';
import { messagingKeys } from '@/lib/queries/messaging';
import { useAcceptOfferMutation, useRejectOfferMutation, useWithdrawOfferMutation } from '@/lib/queries/offers';
import { cn } from '@/lib/utils';

import { ConfirmDialog } from './ConfirmDialog';
import { CounterOfferDialog } from './CounterOfferDialog';
import { DealCard } from './DealCard';

export interface OfferCardProps {
  offer: DealOffer;
  role: DealRole;
  ad: DealCardAd | null;
  /** The ad's listed price, already formatted, when the thread knows it. */
  listedPrice: string | null;
  align: 'start' | 'end';
  /** Injected for tests; defaults to the clock. */
  now?: number;
}

const HOUR = 60 * 60 * 1000;
const cardButton = 'rounded-qb-sm';

/** Where the offer stands, in one line for the viewer. */
export function offerOutcome(offer: DealOffer, role: DealRole, now: number): { tone: StatusTone; text: string } | null {
  switch (offer.status) {
    case 'pending': {
      const left = Date.parse(offer.expires_at) - now;
      if (role === offer.proposed_by) return null;
      return left > 0 && left < 24 * HOUR
        ? { tone: 'brand', text: tPlural('orders.card.expires_soon', Math.max(1, Math.ceil(left / HOUR))) }
        : null;
    }
    case 'accepted':
      return { tone: 'success', text: t('orders.card.outcome.offer_accepted') };
    case 'countered':
      return { tone: 'info', text: t('orders.card.outcome.offer_countered') };
    case 'rejected':
      return { tone: 'danger', text: t('orders.card.outcome.offer_rejected') };
    case 'withdrawn':
      return { tone: 'danger', text: t('orders.card.outcome.offer_withdrawn') };
    case 'expired':
      return { tone: 'danger', text: t('orders.card.outcome.offer_expired') };
  }
}

/**
 * An offer or counter-offer in the chat. The side that did not propose it
 * accepts, counters or rejects; the proposer can withdraw it while pending.
 */
export function OfferCard({ offer, role, ad, listedPrice, align, now = Date.now() }: OfferCardProps) {
  const queryClient = useQueryClient();
  const accept = useAcceptOfferMutation();
  const reject = useRejectOfferMutation();
  const withdraw = useWithdrawOfferMutation();
  const [dialog, setDialog] = useState<'accept' | 'counter' | 'withdraw' | null>(null);

  const isResponder = role !== offer.proposed_by;
  const isPending = offer.status === 'pending';
  useDealEvents(
    offer.conversation_id,
    (event, payload) => {
      const answered = event === 'offer.countered' && payloadField(payload, 'offer', 'parent_offer_id') === offer.id;
      if (answered) void queryClient.invalidateQueries({ queryKey: messagingKeys.messages(offer.conversation_id) });
    },
    isPending,
  );

  const busy = accept.isPending || reject.isPending || withdraw.isPending;
  const amount = formatMoney(offer.amount, offer.currency);

  const run = async (action: () => Promise<unknown>, success: string) => {
    try {
      await action();
      setDialog(null);
      toast.success(success);
    } catch (error) {
      if (!isHandledGlobally(error)) toast.error(dealErrorMessage(error));
    }
  };

  const renderActions = (): ReactNode => {
    if (isPending && isResponder) {
      return (
        <>
          <Button size="sm" className={cardButton} disabled={busy} onClick={() => setDialog('accept')}>
            {t('orders.card.accept')}
          </Button>
          <Button size="sm" variant="outline" className={cardButton} disabled={busy} onClick={() => setDialog('counter')}>
            {t('orders.card.counter')}
          </Button>
          <Button
            size="sm"
            variant="muted"
            className={cardButton}
            disabled={busy}
            aria-busy={reject.isPending}
            onClick={() => run(() => reject.mutateAsync(offer.id), t('orders.card.toast.offer_rejected'))}
          >
            {t('orders.card.reject')}
          </Button>
        </>
      );
    }
    if (isPending) {
      return (
        <Button size="sm" variant="muted" className={cardButton} disabled={busy} onClick={() => setDialog('withdraw')}>
          {t('orders.card.withdraw')}
        </Button>
      );
    }
    if (offer.status === 'accepted') {
      return (
        <Link href={`/account/orders?role=${role}`} className={cn(buttonVariants({ size: 'sm', variant: 'outline' }), cardButton)}>
          {t('orders.card.view_orders')}
        </Link>
      );
    }
    return null;
  };

  return (
    <>
      <DealCard
        kind="offer"
        title={t(offer.counter_round > 0 ? 'orders.card.counter_title' : 'orders.card.offer_title')}
        status={{ tone: OFFER_TONE[offer.status], label: t(`orders.status.offer.${offer.status}`) }}
        ad={ad}
        price={amount}
        priceNote={listedPrice ? t('orders.card.listed', { price: listedPrice }) : undefined}
        createdAt={offer.created_at}
        note={
          offer.note
            ? { label: t(offer.proposed_by === 'buyer' ? 'orders.card.note_buyer' : 'orders.card.note_seller'), text: offer.note }
            : null
        }
        outcome={offerOutcome(offer, role, now)}
        actions={renderActions()}
        align={align}
      />

      {isPending && isResponder ? (
        <>
          <ConfirmDialog
            open={dialog === 'accept'}
            onOpenChange={(open) => setDialog(open ? 'accept' : null)}
            title={t('orders.card.accept_offer_confirm.title')}
            description={t('orders.card.accept_offer_confirm.description', { amount })}
            confirmLabel={t('orders.card.accept_offer_confirm.confirm')}
            cancelLabel={t('common.cancel')}
            busy={accept.isPending}
            onConfirm={() => run(() => accept.mutateAsync(offer.id), t('orders.card.toast.offer_accepted'))}
          />
          <CounterOfferDialog offer={offer} open={dialog === 'counter'} onOpenChange={(open) => setDialog(open ? 'counter' : null)} />
        </>
      ) : null}

      {isPending && !isResponder ? (
        <ConfirmDialog
          open={dialog === 'withdraw'}
          onOpenChange={(open) => setDialog(open ? 'withdraw' : null)}
          title={t('orders.card.withdraw_confirm.title')}
          description={t('orders.card.withdraw_confirm.description')}
          confirmLabel={t('orders.card.withdraw_confirm.confirm')}
          cancelLabel={t('orders.card.withdraw_confirm.keep')}
          tone="danger"
          busy={withdraw.isPending}
          onConfirm={() => run(() => withdraw.mutateAsync(offer.id), t('orders.card.toast.offer_withdrawn'))}
        />
      ) : null}
    </>
  );
}

