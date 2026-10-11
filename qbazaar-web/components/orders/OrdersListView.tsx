'use client';

import Link from 'next/link';
import { parseAsStringEnum, useQueryState } from 'nuqs';
import { ArrowUpRight, CreditCard, Package, ShoppingBag } from 'lucide-react';

import { buttonVariants } from '@/components/design-system/Button';
import { EmptyState } from '@/components/design-system/EmptyState';
import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { FieldSelect } from '@/components/design-system/FieldSelect';
import { Tab, TabList, TabPanel, Tabs } from '@/components/design-system/Tabs';
import type { DealRole, Order, OrderStatus } from '@/lib/api/commerce-types';
import { t } from '@/lib/i18n/messages';
import { formatDate, isoDate } from '@/lib/orders/dates';
import { formatMoney } from '@/lib/orders/money';
import { ORDER_STATUS_TONE } from '@/lib/orders/status';
import { isolate } from '@/lib/orders/text';
import { useOrdersQuery } from '@/lib/queries/orders';
import { cn } from '@/lib/utils';

import { AccountPageFrame } from './AccountPageFrame';
import { HeaderButtonLabel, pageHeaderButton } from './AccountPageHeader';
import { panelClass } from './CheckoutPanel';
import { orderNumber } from './order-number';
import { PageState } from './PageState';
import { StatusPill } from './StatusPill';
import { LoadMore, TableCard, Th, tableClasses as tc } from './TableCard';

const ROLES: DealRole[] = ['buyer', 'seller'];
const STATUSES: OrderStatus[] = ['created', 'awaiting_handover', 'completed', 'cancelled', 'disputed'];

/** `/account/orders`: purchases and sales in two tabs (sales overview 502:21437, pill tabs 455:14636). */
export function OrdersListView() {
  const [role, setRole] = useQueryState('role', parseAsStringEnum<DealRole>(ROLES).withDefault('buyer'));
  const [status, setStatus] = useQueryState('status', parseAsStringEnum<OrderStatus>(STATUSES));

  return (
    <AccountPageFrame
      breadcrumb={[{ label: t('orders.list.title') }]}
      title={t('orders.list.title')}
      actions={
        <>
          <Link href="/account/wallet/bank-accounts" className={cn(buttonVariants({ variant: 'outline' }), pageHeaderButton)}>
            <CreditCard aria-hidden="true" />
            <HeaderButtonLabel>{t('orders.wallet.payment_method')}</HeaderButtonLabel>
          </Link>
          <Link href="/account/wallet/withdrawals" className={cn(buttonVariants({ variant: 'outline' }), pageHeaderButton, 'max-qb-tablet:hidden')}>
            <ArrowUpRight aria-hidden="true" />
            <HeaderButtonLabel>{t('orders.wallet.withdraw')}</HeaderButtonLabel>
          </Link>
        </>
      }
    >
      <Tabs value={role} onValueChange={(value) => void setRole(value as DealRole)}>
        <div className="flex flex-col gap-4 qb-tablet:flex-row qb-tablet:items-end qb-tablet:justify-between">
          <TabList aria-label={t('orders.list.tabs_label')}>
            <Tab value="buyer">{t('orders.list.purchases')}</Tab>
            <Tab value="seller">{t('orders.list.sales')}</Tab>
          </TabList>
          <div className="flex items-center gap-3 text-qb-caption text-qb-ink-body">
            <span id="orders-status-filter" className="shrink-0">
              {t('orders.list.status_filter')}
            </span>
            <FieldSelect
              label={t('orders.list.status_filter')}
              aria-labelledby="orders-status-filter"
              value={status ?? ''}
              options={[
                { value: '', label: t('orders.list.all_statuses') },
                ...STATUSES.map((value) => ({ value, label: t(`orders.status.order.${value}`) })),
              ]}
              onChange={(next) => void setStatus(STATUSES.find((value) => value === next) ?? null)}
              className="h-11 w-auto min-w-48 text-qb-caption"
            />
          </div>
        </div>
        {ROLES.map((value) => (
          <TabPanel key={value} value={value}>
            {value === role ? <OrdersTable role={value} status={status ?? undefined} /> : null}
          </TabPanel>
        ))}
      </Tabs>
    </AccountPageFrame>
  );
}

function OrdersTable({ role, status }: { role: DealRole; status?: OrderStatus }) {
  const query = useOrdersQuery(role, status);
  const orders = query.data?.pages.flatMap((page) => page.data) ?? [];
  const title = t(role === 'buyer' ? 'orders.list.caption_purchases' : 'orders.list.caption_sales');

  if (query.isPending) return <PageState kind="loading" />;
  if (query.isError) return <PageState kind="error" onRetry={() => query.refetch()} />;
  if (orders.length === 0) return <OrdersEmpty role={role} />;

  return (
    <TableCard title={title} titleId={`orders-${role}`}>
      <table className={tc.table} aria-labelledby={`orders-${role}`}>
        <thead>
          <tr className={tc.headRow}>
            <Th>{t('orders.list.columns.item')}</Th>
            <Th className={tc.wide}>{t('orders.list.columns.date')}</Th>
            <Th className="hidden qb-desktop:table-cell">{t('orders.list.columns.payment')}</Th>
            <Th className={tc.wide}>{t('orders.list.columns.status')}</Th>
            <Th className="text-end">{t('orders.list.columns.total')}</Th>
          </tr>
        </thead>
        <tbody>
          {orders.map((order) => (
            <OrderRow key={order.id} order={order} />
          ))}
        </tbody>
      </table>
      {query.hasNextPage ? <LoadMore onClick={() => void query.fetchNextPage()} loading={query.isFetchingNextPage} /> : null}
    </TableCard>
  );
}

function OrderRow({ order }: { order: Order }) {
  const tone = ORDER_STATUS_TONE[order.status];
  const label = t(`orders.status.order.${order.status}`);
  const number = orderNumber(order.id);
  const date = (
    <time dateTime={isoDate(order.created_at)} className="whitespace-nowrap">
      {formatDate(order.created_at)}
    </time>
  );
  const fulfillment = order.fulfillment ? ` · ${t(`orders.common.${order.fulfillment}`)}` : '';

  return (
    <tr className={tc.row}>
      <td className={tc.td}>
        <div className="flex items-center gap-3">
          <span
            aria-hidden="true"
            className="flex size-[50px] shrink-0 items-center justify-center rounded-qb-sm bg-qb-fill text-qb-ink-subtle"
          >
            <Icon icon={Package} />
          </span>
          <div className="min-w-0">
            <Link
              href={`/account/orders/${encodeURIComponent(order.id)}`}
              aria-label={`${isolate(order.ad.title)}, ${t('orders.list.open', { number })}`}
              className={cn('line-clamp-2 rounded-qb-xs hover:underline', tc.itemTitle, focusRing)}
            >
              <bdi>{order.ad.title}</bdi>
            </Link>
            <p className={tc.itemMeta}>
              {t('orders.common.order_number', { number })}
              {order.quantity > 1 ? ` · × ${order.quantity}` : null}
              <span className="qb-tablet:hidden"> · {date}</span>
            </p>
          </div>
        </div>
      </td>
      <td className={cn(tc.td, tc.wide)}>{date}</td>
      <td className={cn(tc.td, 'hidden qb-desktop:table-cell')}>
        {t('orders.common.cash')}
        {fulfillment}
      </td>
      <td className={cn(tc.td, tc.wide)}>
        <StatusPill tone={tone}>{label}</StatusPill>
      </td>
      <td className={cn(tc.td, tc.amount)}>
        {formatMoney(order.total, order.currency)}
        <div className="mt-1 qb-tablet:hidden">
          <StatusPill tone={tone} compact>
            {label}
          </StatusPill>
        </div>
      </td>
    </tr>
  );
}

function OrdersEmpty({ role }: { role: DealRole }) {
  const isBuyer = role === 'buyer';
  return (
    <div className={panelClass}>
      <EmptyState
        icon={<Icon icon={isBuyer ? ShoppingBag : Package} size="lg" />}
        title={t(isBuyer ? 'orders.list.empty_purchases' : 'orders.list.empty_sales')}
        description={t(isBuyer ? 'orders.list.empty_purchases_body' : 'orders.list.empty_sales_body')}
        action={
          <Link href={isBuyer ? '/ads' : '/account/ads'} className={buttonVariants({ size: 'sm' })}>
            {t(isBuyer ? 'orders.list.browse' : 'orders.list.my_ads')}
          </Link>
        }
      />
    </div>
  );
}
