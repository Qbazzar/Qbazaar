'use client';

import { useState, type ReactNode } from 'react';
import Link from 'next/link';
import { useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';

import { Button, buttonVariants } from '@/components/design-system/Button';
import { showDesignToast } from '@/components/design-system/design-toast';
import type { DealCardAd, DealOffer, DealRole, Order } from '@/lib/api/commerce-types';
import { payloadField, useDealEvents } from '@/lib/echo/useDealEvents';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';
import { dealErrorMessage, isHandledGlobally } from '@/lib/orders/errors';
import { formatMoney } from '@/lib/orders/money';
import { OFFER_TONE, type StatusTone } from '@/lib/orders/status';
import { messagingKeys } from '@/lib/queries/messaging';
import { useAcceptOfferMutation, useRejectOfferMutation, useWithdrawOfferMutation } from '@/lib/queries/offers';
import { useOrdersQuery } from '@/lib/queries/orders';
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

type Outcome = { tone: StatusTone; text: string } | null;

/** Where a pending or closed offer stands, in one line for the viewer. */
export function offerOutcome(offer: DealOffer, role: DealRole, now: number): Outcome {
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

export interface AcceptedOfferState {
  /** The buyer checked out: the pill reads "Paid". */
  paid: boolean;
  outcome: Outcome;
  action: 'checkout' | 'view_order' | 'view_orders' | null;
}

/**
 * An accepted offer follows the order it placed (721:42157, 721:41875): the
 * buyer gets "Proceed to payment", the seller waits for the payment, and a
 * paid order shows "Payment Successful" alone. Without the order (beyond the
 * first page of the viewer's orders) the card links to the orders instead.
 */
export function acceptedOfferState(role: DealRole, order: Order | undefined, loading: boolean): AcceptedOfferState {
  if (loading) return { paid: false, outcome: null, action: null };
  switch (order?.status) {
    case 'created':
      return role === 'buyer'
        ? { paid: false, outcome: null, action: 'checkout' }
        : { paid: false, outcome: { tone: 'success', text: t('orders.card.outcome.request_accepted_seller') }, action: null };
    case 'awaiting_handover':
    case 'completed':
      return { paid: true, outcome: { tone: 'success', text: t('orders.card.outcome.request_paid') }, action: null };
    case 'cancelled':
      return { paid: false, outcome: { tone: 'danger', text: t('orders.card.outcome.request_order_cancelled') }, action: 'view_order' };
    case 'disputed':
      return { paid: true, outcome: { tone: 'danger', text: t('orders.status.order.disputed') }, action: 'view_order' };
    default:
      return { paid: false, outcome: { tone: 'success', text: t('orders.card.outcome.offer_accepted') }, action: 'view_orders' };
  }
}

/** The order an accepted offer placed; the API links it by `source_id`, so it is found in the viewer's orders. */
function useOfferOrder(offer: DealOffer, role: DealRole): { order: Order | undefined; loading: boolean } {
  const accepted = offer.status === 'accepted';
  const orders = useOrdersQuery(role, undefined, { enabled: accepted });
  const order = orders.data?.pages
    .flatMap((page) => page.data)
    .find((item) => item.source === 'offer' && item.source_id === offer.id);
  return { order, loading: accepted && orders.isLoading };
}

/**
 * An offer or counter-offer in the chat. The side that did not propose it
 * accepts, counters or rejects; the proposer can cancel it while pending.
 */
export function OfferCard({ offer, role, ad, listedPrice, align, now = Date.now() }: OfferCardProps) {
  const queryClient = useQueryClient();
  const accept = useAcceptOfferMutation();
  const reject = useRejectOfferMutation();
  const withdraw = useWithdrawOfferMutation();
  const [dialog, setDialog] = useState<'accept' | 'counter' | 'withdraw' | null>(null);
  const { order, loading: orderLoading } = useOfferOrder(offer, role);

  const isResponder = role !== offer.proposed_by;
  const isPending = offer.status === 'pending';
  const accepted = offer.status === 'accepted' ? acceptedOfferState(role, order, orderLoading) : null;
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
      showDesignToast(success);
    } catch (error) {
      if (!isHandledGlobally(error)) toast.error(dealErrorMessage(error));
    }
  };

  const renderActions = (): ReactNode => {
    if (isPending && isResponder) {
      return (
        <>
          <Button size="sm" className={cardButton} disabled={busy} onClick={() => setDialog('accept')}>
            {t('orders.card.accept_request')}
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
      // 721:42157: the proposer has one full-width outlined "Cancel".
      return (
        <Button size="sm" variant="secondary" className={cardButton} disabled={busy} onClick={() => setDialog('withdraw')}>
          {t('orders.card.cancel_request')}
        </Button>
      );
    }
    const orderId = encodeURIComponent(order?.id ?? '');
    switch (accepted?.action) {
      case 'checkout':
        return (
          <Link href={`/checkout/${orderId}`} className={cn(buttonVariants({ size: 'sm' }), cardButton)}>
            {t('orders.card.checkout')}
          </Link>
        );
      case 'view_order':
        return (
          <Link href={`/account/orders/${orderId}`} className={cn(buttonVariants({ size: 'sm', variant: 'outline' }), cardButton)}>
            {t('orders.card.view_order')}
          </Link>
        );
      case 'view_orders':
        return (
          <Link href={`/account/orders?role=${role}`} className={cn(buttonVariants({ size: 'sm', variant: 'outline' }), cardButton)}>
            {t('orders.card.view_orders')}
          </Link>
        );
      default:
        return null;
    }
  };

  return (
    <>
      <DealCard
        kind="offer"
        title={t(offer.counter_round > 0 ? 'orders.card.counter_title' : 'orders.card.offer_title')}
        status={
          accepted?.paid
            ? { tone: 'success', label: t('orders.status.request.paid') }
            : { tone: OFFER_TONE[offer.status], label: t(`orders.status.offer.${offer.status}`) }
        }
        ad={ad}
        price={amount}
        priceNote={listedPrice ? t('orders.card.listed', { price: listedPrice }) : undefined}
        createdAt={offer.created_at}
        note={
          offer.note
            ? { label: t(offer.proposed_by === 'buyer' ? 'orders.card.note_buyer' : 'orders.card.note_seller'), text: offer.note }
            : null
        }
        outcome={accepted ? accepted.outcome : offerOutcome(offer, role, now)}
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

