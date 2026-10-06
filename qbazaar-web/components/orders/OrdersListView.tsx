'use client';

import Link from 'next/link';
import { parseAsStringEnum, useQueryState } from 'nuqs';
import { Package, ShoppingBag, Wallet } from 'lucide-react';

import { buttonVariants } from '@/components/design-system/Button';
import { EmptyState } from '@/components/design-system/EmptyState';
import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { Select } from '@/components/design-system/Input';
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
import { HeaderButtonLabel, headerButton } from './AccountPageHeader';
import { orderNumber } from './order-number';
import { PageState } from './PageState';
import { StatusPill } from './StatusPill';
import { LoadMore, TableCard, tableClasses as tc } from './TableCard';

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
        <Link href="/account/wallet" className={cn(buttonVariants({ variant: 'outline' }), headerButton)}>
          <Wallet aria-hidden="true" />
          <HeaderButtonLabel>{t('orders.list.wallet')}</HeaderButtonLabel>
        </Link>
      }
    >
      <Tabs value={role} onValueChange={(value) => void setRole(value as DealRole)}>
        <div className="flex flex-col gap-4 qb-tablet:flex-row qb-tablet:items-end qb-tablet:justify-between">
          <TabList aria-label={t('orders.list.tabs_label')}>
            <Tab value="buyer">{t('orders.list.purchases')}</Tab>
            <Tab value="seller">{t('orders.list.sales')}</Tab>
          </TabList>
          <label className="flex items-center gap-3 text-qb-caption text-qb-ink-body">
            <span className="shrink-0">{t('orders.list.status_filter')}</span>
            <Select
              value={status ?? ''}
              onChange={(event) => void setStatus((event.target.value || null) as OrderStatus | null)}
              className="h-11 min-w-48 text-qb-caption"
            >
              <option value="">{t('orders.list.all_statuses')}</option>
              {STATUSES.map((value) => (
                <option key={value} value={value}>
                  {t(`orders.status.order.${value}`)}
                </option>
              ))}
            </Select>
          </label>
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
            <th scope="col" className={tc.th}>
              {t('orders.list.columns.item')}
            </th>
            <th scope="col" className={cn(tc.th, tc.wide)}>
              {t('orders.list.columns.date')}
            </th>
            <th scope="col" className={cn(tc.th, 'hidden qb-desktop:table-cell')}>
              {t('orders.list.columns.payment')}
            </th>
            <th scope="col" className={cn(tc.th, tc.wide)}>
              {t('orders.list.columns.status')}
            </th>
            <th scope="col" className={cn(tc.th, 'text-end')}>
              {t('orders.list.columns.total')}
            </th>
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
  const status = <StatusPill tone={ORDER_STATUS_TONE[order.status]}>{t(`orders.status.order.${order.status}`)}</StatusPill>;
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
              className={cn('line-clamp-2 rounded-qb-xs font-semibold text-qb-ink-title hover:underline', focusRing)}
            >
              <bdi>{order.ad.title}</bdi>
            </Link>
            <p className="mt-0.5 text-qb-micro font-normal text-qb-ink-subtle qb-desktop:text-qb-body">
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
      <td className={cn(tc.td, tc.wide)}>{status}</td>
      <td className={cn(tc.td, tc.amount)}>
        {formatMoney(order.total, order.currency)}
        <div className="mt-1 qb-tablet:hidden">{status}</div>
      </td>
    </tr>
  );
}

function OrdersEmpty({ role }: { role: DealRole }) {
  const isBuyer = role === 'buyer';
  return (
    <div className="rounded-qb-2xl border border-qb-line bg-qb-surface shadow-qb-card">
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
