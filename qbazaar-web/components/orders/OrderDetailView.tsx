'use client';

import Link from 'next/link';
import { ArrowLeft, Info, ShieldAlert } from 'lucide-react';

import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { Notice } from '@/components/design-system/Notice';
import type { Order } from '@/lib/api/commerce-types';
import { t } from '@/lib/i18n/messages';
import { addressLines } from '@/lib/orders/address';
import { formatDate, formatDateTime } from '@/lib/orders/dates';
import { formatMoney, formatRate, isPositiveAmount } from '@/lib/orders/money';
import { ORDER_STATUS_TONE, orderActions } from '@/lib/orders/status';
import { isolate } from '@/lib/orders/text';
import { useOrderQuery } from '@/lib/queries/orders';
import { cn } from '@/lib/utils';

import { AccountPageFrame } from './AccountPageFrame';
import { CheckoutPanel, panelClass } from './CheckoutPanel';
import { OrderActionsBar } from './OrderActionsBar';
import { orderNumber } from './order-number';
import { OrderTimeline } from './OrderTimeline';
import { PageState } from './PageState';
import { StatusPill } from './StatusPill';

/** `/account/orders/{id}`: where the order stands and what each side does next. */
export function OrderDetailView({ orderId }: { orderId: string }) {
  const query = useOrderQuery(orderId);

  return (
    <AccountPageFrame breadcrumb={[{ label: t('orders.list.title'), href: '/account/orders' }, { label: t('orders.detail.title') }]}>
      <Link
        href="/account/orders"
        className={cn(
          'inline-flex items-center gap-2 self-start rounded-qb-xs text-qb-caption text-qb-ink-secondary hover:text-qb-ink qb-tablet:hidden',
          focusRing,
        )}
      >
        <Icon icon={ArrowLeft} size="sm" flipInRtl />
        {t('orders.detail.back')}
      </Link>
      {query.isPending ? (
        <PageState kind="loading" />
      ) : query.isError ? (
        query.error.status === 404 ? (
          <PageState kind="empty" message={t('orders.detail.not_found')} />
        ) : (
          <PageState kind="error" onRetry={() => query.refetch()} />
        )
      ) : (
        <OrderDetail order={query.data} />
      )}
    </AccountPageFrame>
  );
}

function nextStepKey(order: Order): string {
  const role = order.viewer_role;
  switch (order.status) {
    case 'created':
      return `${role}_created`;
    case 'awaiting_handover':
      return `${role}_awaiting`;
    case 'completed':
      if (role === 'seller') return 'seller_completed';
      return orderActions(order).reportProblem ? 'buyer_completed' : 'buyer_completed_closed';
    case 'cancelled':
      return 'cancelled';
    case 'disputed':
      return 'disputed';
  }
}

function OrderDetail({ order }: { order: Order }) {
  const number = orderNumber(order.id);

  return (
    <>
      <header className="flex flex-col gap-3 qb-tablet:flex-row qb-tablet:items-start qb-tablet:justify-between">
        <div className="min-w-0">
          <h1 className="font-qb text-qb-h4 font-semibold tracking-normal text-qb-ink qb-desktop:text-qb-h2">
            <bdi>{order.ad.title}</bdi>
          </h1>
          <p className="mt-2 text-qb-caption text-qb-ink-subtle">
            {t('orders.common.order_number', { number })} · {t('orders.detail.placed_on', { date: formatDate(order.created_at) })} ·{' '}
            {t(order.viewer_role === 'buyer' ? 'orders.detail.role_buyer' : 'orders.detail.role_seller')}
          </p>
        </div>
        <StatusPill tone={ORDER_STATUS_TONE[order.status]} className="self-start">
          {t(`orders.status.order.${order.status}`)}
        </StatusPill>
      </header>

      <div className="flex flex-col gap-6 qb-desktop:flex-row qb-desktop:items-start">
        <div className="flex min-w-0 flex-1 flex-col gap-6">
          <CheckoutPanel title={t('orders.detail.progress')} titleId="order-progress">
            <div className="flex flex-col gap-6">
              <OrderTimeline order={order} />
              <Notice tone={order.status === 'disputed' ? 'danger' : 'info'} icon={<Info aria-hidden="true" />} role="status">
                {t(`orders.detail.next.${nextStepKey(order)}`, {
                  date: order.report_problem_until ? formatDateTime(order.report_problem_until) : '',
                })}
              </Notice>
              {order.cancellation_reason && !order.dispute ? (
                <p className="text-qb-caption text-qb-ink-secondary">
                  {t('orders.detail.cancellation_reason', { reason: isolate(order.cancellation_reason) })}
                </p>
              ) : null}
              <OrderActionsBar order={order} />
              {order.status === 'completed' && order.viewer_role === 'seller' ? (
                <Link href="/account/wallet" className={cn('self-start rounded-qb-xs text-qb-caption font-semibold text-qb-brand underline underline-offset-2', focusRing)}>
                  {t('orders.detail.wallet')}
                </Link>
              ) : null}
            </div>
          </CheckoutPanel>

          {order.dispute ? <DisputePanel order={order} /> : null}
        </div>

        <OrderSummaryPanel order={order} />
      </div>
    </>
  );
}

function DisputePanel({ order }: { order: Order }) {
  const dispute = order.dispute;
  if (!dispute) return null;
  const resolved = dispute.resolution !== null;

  return (
    <CheckoutPanel title={t('orders.detail.dispute_title')} titleId="order-dispute">
      <dl className="flex flex-col gap-4 text-qb-caption">
        {dispute.reason ? (
          <div>
            <dt className="text-qb-ink-subtle">{t('orders.detail.dispute_reason')}</dt>
            <dd dir="auto" className="mt-1 text-start break-words whitespace-pre-wrap text-qb-ink">
              {dispute.reason}
            </dd>
          </div>
        ) : null}
        <div>
          <dt className="sr-only">{t('orders.detail.step.resolved')}</dt>
          <dd>
            <Notice
              tone={resolved ? (dispute.resolution === 'completed' ? 'success' : 'neutral') : 'brand'}
              icon={<ShieldAlert aria-hidden="true" />}
              title={
                resolved
                  ? t(dispute.resolution === 'completed' ? 'orders.detail.ruling_completed' : 'orders.detail.ruling_cancelled')
                  : t('orders.detail.dispute_open')
              }
            >
              {dispute.resolution_note ? (
                <p>
                  <span className="font-medium">{t('orders.detail.ruling_note')}: </span>
                  <bdi>{dispute.resolution_note}</bdi>
                </p>
              ) : null}
              {dispute.resolved_at ? <p className="mt-1 text-qb-ink-subtle">{formatDateTime(dispute.resolved_at)}</p> : null}
            </Notice>
          </dd>
        </div>
      </dl>
    </CheckoutPanel>
  );
}

function OrderSummaryPanel({ order }: { order: Order }) {
  const rows: Array<{ label: string; value: string }> = [
    {
      label: t('orders.detail.unit_price'),
      value:
        order.quantity > 1
          ? t('orders.card.quantity_line', { count: order.quantity, price: formatMoney(order.unit_price, order.currency) })
          : formatMoney(order.unit_price, order.currency),
    },
    {
      label: t('orders.detail.fulfillment'),
      value: order.fulfillment ? t(`orders.common.${order.fulfillment}`) : t('orders.detail.not_chosen'),
    },
  ];
  if (order.fulfillment === 'delivery') {
    rows.push({
      label: t('orders.detail.shipping'),
      value: isPositiveAmount(order.shipping_fee) ? formatMoney(order.shipping_fee, order.currency) : t('orders.checkout.free'),
    });
  }
  rows.push({ label: t('orders.detail.payment'), value: t('orders.detail.payment_cash') });

  return (
    <section aria-labelledby="order-summary" className={cn(panelClass, 'p-4 qb-tablet:p-6 qb-desktop:w-[380px] qb-desktop:shrink-0')}>
      <h2 id="order-summary" className="font-qb text-qb-body-lg font-medium tracking-normal text-qb-ink-title">
        {t('orders.detail.summary')}
      </h2>
      <dl className="mt-4 flex flex-col gap-3 text-qb-caption">
        {rows.map((row) => (
          <div key={row.label} className="flex items-start justify-between gap-4">
            <dt className="text-qb-ink-subtle">{row.label}</dt>
            <dd className="text-end font-semibold text-qb-ink">{row.value}</dd>
          </div>
        ))}
        <div className="flex items-center justify-between gap-4 border-t border-qb-line pt-3">
          <dt className="font-medium text-qb-ink-secondary">{t('orders.detail.total')}</dt>
          <dd className="text-qb-body font-semibold text-qb-ink">{formatMoney(order.total, order.currency)}</dd>
        </div>
        {order.commission ? (
          <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 rounded-qb-md bg-qb-fill p-3">
            <dt className="text-qb-ink-secondary">{t('orders.detail.commission', { rate: formatRate(order.commission.rate) })}</dt>
            <dd className="font-semibold text-qb-ink">{formatMoney(order.commission.amount, order.currency)}</dd>
            <dd className="basis-full text-qb-micro text-qb-ink-subtle">{t('orders.detail.commission_hint')}</dd>
          </div>
        ) : null}
      </dl>
      {order.delivery_address ? (
        <div className="mt-4 border-t border-qb-line pt-4">
          <h3 className="font-qb text-qb-caption font-medium tracking-normal text-qb-ink-subtle">{t('orders.detail.address')}</h3>
          {/* Each line keeps its own direction; the block keeps the page's alignment. */}
          <address className="mt-2 text-start text-qb-caption leading-relaxed text-qb-ink not-italic">
            <span className="block font-semibold">
              <bdi>{order.delivery_address.full_name}</bdi>
            </span>
            {addressLines(order.delivery_address).map((line) => (
              <span key={line} className="block">
                <bdi>{line}</bdi>
              </span>
            ))}
          </address>
        </div>
      ) : null}
      {order.ad.id ? (
        <Link
          href={`/ads/${encodeURIComponent(order.ad.id)}`}
          className={cn('mt-4 inline-block rounded-qb-xs text-qb-caption font-semibold text-qb-brand underline underline-offset-2', focusRing)}
        >
          {t('orders.detail.view_ad')}
        </Link>
      ) : null}
    </section>
  );
}
