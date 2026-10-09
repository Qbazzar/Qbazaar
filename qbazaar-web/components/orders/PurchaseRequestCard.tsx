'use client';

import { useState, type ReactNode } from 'react';
import Link from 'next/link';
import { useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';

import { Button, buttonVariants } from '@/components/design-system/Button';
import type { DealCardAd, DealRole, OrderStatus, PurchaseRequest } from '@/lib/api/commerce-types';
import { payloadField, useDealEvents } from '@/lib/echo/useDealEvents';
import { useIdempotencyKey } from '@/hooks/useIdempotencyKey';
import { t } from '@/lib/i18n/messages';
import { dealErrorMessage, isHandledGlobally } from '@/lib/orders/errors';
import { formatMoney } from '@/lib/orders/money';
import { PURCHASE_REQUEST_TONE, type StatusTone } from '@/lib/orders/status';
import { messagingKeys } from '@/lib/queries/messaging';
import { useOrderQuery } from '@/lib/queries/orders';
import {
  useAcceptPurchaseRequestMutation,
  useCancelPurchaseRequestMutation,
  useRejectPurchaseRequestMutation,
} from '@/lib/queries/purchase-requests';
import { cn } from '@/lib/utils';

import { ConfirmDialog } from './ConfirmDialog';
import { DealCard } from './DealCard';

export interface PurchaseRequestCardProps {
  request: PurchaseRequest;
  role: DealRole;
  ad: DealCardAd | null;
  align: 'start' | 'end';
}

const cardButton = 'rounded-qb-sm';

/** Where the request stands, in one line for the viewer; an accepted one follows its order. */
export function requestOutcome(
  request: PurchaseRequest,
  role: DealRole,
  orderStatus?: OrderStatus,
): { tone: StatusTone; text: string } | null {
  switch (request.status) {
    case 'pending':
      return null;
    case 'accepted':
      return acceptedOutcome(role, orderStatus);
    case 'paid':
      return { tone: 'success', text: t('orders.card.outcome.request_paid', { total: formatMoney(request.total, request.currency) }) };
    case 'rejected':
      return { tone: 'danger', text: t('orders.card.outcome.request_rejected') };
    case 'cancelled':
      if (!request.cancelled_by) return { tone: 'danger', text: t('orders.card.outcome.request_closed') };
      return {
        tone: 'danger',
        text: t(role === 'buyer' ? 'orders.card.outcome.request_cancelled_self' : 'orders.card.outcome.request_cancelled'),
      };
  }
}

function acceptedOutcome(role: DealRole, orderStatus?: OrderStatus): { tone: StatusTone; text: string } | null {
  if (orderStatus === 'cancelled') return { tone: 'danger', text: t('orders.card.outcome.request_order_cancelled') };
  // 721:42157: the buyer gets "Proceed to payment" instead of a state line.
  if (orderStatus === 'created') {
    return role === 'buyer' ? null : { tone: 'success', text: t('orders.card.outcome.request_accepted_seller') };
  }
  if (orderStatus === 'awaiting_handover') return { tone: 'success', text: t(`orders.card.outcome.request_checked_out_${role}`) };
  return { tone: 'success', text: t('orders.card.outcome.request_accepted') };
}

/** The "Buy Now" card in the chat, with every state and the actions of each side. */
export function PurchaseRequestCard({ request, role, ad, align }: PurchaseRequestCardProps) {
  const queryClient = useQueryClient();
  const accept = useAcceptPurchaseRequestMutation();
  const reject = useRejectPurchaseRequestMutation();
  const cancel = useCancelPurchaseRequestMutation();
  const { key: acceptKey, renew: renewAcceptKey } = useIdempotencyKey();
  const [dialog, setDialog] = useState<'accept' | 'cancel' | null>(null);
  const order = useOrderQuery(request.status === 'accepted' && request.order_id ? request.order_id : '');
  const orderStatus = order.data?.status;

  const isLive = request.status === 'pending' || request.status === 'accepted';
  useDealEvents(
    request.conversation_id,
    (_event, payload) => {
      if (payloadField(payload, 'purchase_request') !== request.id) return;
      void queryClient.invalidateQueries({ queryKey: messagingKeys.messages(request.conversation_id) });
    },
    isLive,
  );

  const busy = accept.isPending || reject.isPending || cancel.isPending;
  const total = formatMoney(request.total, request.currency);

  const run = async (action: () => Promise<unknown>, success: string, onSuccess?: () => void) => {
    try {
      await action();
      onSuccess?.();
      setDialog(null);
      toast.success(success);
    } catch (error) {
      if (!isHandledGlobally(error)) toast.error(dealErrorMessage(error));
    }
  };

  const renderActions = (): ReactNode => {
    if (request.status === 'pending' && role === 'seller') {
      return (
        <>
          <Button size="sm" className={cardButton} disabled={busy} onClick={() => setDialog('accept')}>
            {t('orders.card.accept_request')}
          </Button>
          <Button
            size="sm"
            variant="muted"
            className={cardButton}
            disabled={busy}
            aria-busy={reject.isPending}
            onClick={() => run(() => reject.mutateAsync(request.id), t('orders.card.toast.request_declined'))}
          >
            {t('orders.card.decline')}
          </Button>
        </>
      );
    }
    if (request.status === 'pending') {
      // 721:42157: the buyer has one full-width outlined "Cancel".
      return (
        <Button size="sm" variant="secondary" className={cardButton} disabled={busy} onClick={() => setDialog('cancel')}>
          {t('orders.card.cancel_request')}
        </Button>
      );
    }
    const orderId = request.order_id;
    if (!orderId || (request.status !== 'accepted' && request.status !== 'paid')) return null;
    const orderHref = `/account/orders/${encodeURIComponent(orderId)}`;
    if (request.status === 'accepted' && role === 'buyer' && orderStatus === 'created') {
      // 721:42157: one full-width "Proceed to payment".
      return (
        <Link href={`/checkout/${encodeURIComponent(orderId)}`} className={cn(buttonVariants({ size: 'sm' }), cardButton)}>
          {t('orders.card.checkout')}
        </Link>
      );
    }
    return (
      <Link href={orderHref} className={cn(buttonVariants({ size: 'sm', variant: 'outline' }), cardButton)}>
        {t('orders.card.view_order')}
      </Link>
    );
  };

  return (
    <>
      <DealCard
        kind="purchase"
        title={t('orders.card.purchase_title')}
        status={{ tone: PURCHASE_REQUEST_TONE[request.status], label: t(`orders.status.request.${request.status}`) }}
        ad={ad}
        price={total}
        priceNote={
          request.quantity > 1
            ? t('orders.card.quantity_line', { count: request.quantity, price: formatMoney(request.unit_price, request.currency) })
            : undefined
        }
        createdAt={request.created_at}
        note={request.note ? { label: t('orders.card.note_buyer'), text: request.note } : null}
        outcome={requestOutcome(request, role, orderStatus)}
        actions={renderActions()}
        align={align}
      />

      {role === 'seller' ? (
        <ConfirmDialog
          open={dialog === 'accept'}
          onOpenChange={(open) => setDialog(open ? 'accept' : null)}
          title={t('orders.card.accept_request_confirm.title')}
          description={t('orders.card.accept_request_confirm.description', { total })}
          confirmLabel={t('orders.card.accept_request_confirm.confirm')}
          cancelLabel={t('common.cancel')}
          busy={accept.isPending}
          onConfirm={() =>
            run(() => accept.mutateAsync({ id: request.id, idempotencyKey: acceptKey }), t('orders.card.toast.request_accepted'), renewAcceptKey)
          }
        />
      ) : (
        <ConfirmDialog
          open={dialog === 'cancel'}
          onOpenChange={(open) => setDialog(open ? 'cancel' : null)}
          title={t('orders.card.cancel_request_confirm.title')}
          description={t('orders.card.cancel_request_confirm.description')}
          confirmLabel={t('orders.card.cancel_request_confirm.confirm')}
          cancelLabel={t('orders.card.cancel_request_confirm.keep')}
          tone="danger"
          busy={cancel.isPending}
          onConfirm={() => run(() => cancel.mutateAsync(request.id), t('orders.card.toast.request_cancelled'))}
        />
      )}
    </>
  );
}
