'use client';

import { useState } from 'react';
import Link from 'next/link';
import { Receipt } from 'lucide-react';

import { EmptyState } from '@/components/design-system/EmptyState';
import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { FieldSelect } from '@/components/design-system/FieldSelect';
import { orderNumber } from '@/components/orders/order-number';
import { PageState } from '@/components/orders/PageState';
import { LoadMore, TableCard, Th, tableClasses as tc } from '@/components/orders/TableCard';
import type { WalletAccount, WalletEntry } from '@/lib/api/commerce-types';
import { t } from '@/lib/i18n/messages';
import { formatDate, isoDate } from '@/lib/orders/dates';
import { formatMoney } from '@/lib/orders/money';
import { useWalletEntriesQuery } from '@/lib/queries/wallet';
import { cn } from '@/lib/utils';

const ACCOUNTS: WalletAccount[] = ['wallet', 'commission_receivable'];

/** "+QAR 10.00" or "−QAR 10.00", kept left to right so the sign stays with the number in Arabic. */
export function signedAmount(entry: Pick<WalletEntry, 'amount' | 'currency' | 'direction'>): string {
  return `${entry.direction === 'increase' ? '+' : '−'}${formatMoney(entry.amount, entry.currency)}`;
}

/** The ledger statement of the wallet and the commission account, newest first. */
export function StatementTable() {
  const [account, setAccount] = useState<WalletAccount | ''>('');
  const query = useWalletEntriesQuery(account || undefined);
  const entries = query.data?.pages.flatMap((page) => page.data) ?? [];

  return (
    <TableCard
      title={t('orders.wallet.history_title')}
      titleId="wallet-history"
      action={
        <div className="flex items-center gap-3 text-qb-caption text-qb-ink-body">
          <span id="wallet-history-filter" className="shrink-0">
            {t('orders.wallet.filter_label')}
          </span>
          <FieldSelect
            label={t('orders.wallet.filter_label')}
            aria-labelledby="wallet-history-filter"
            value={account}
            options={[
              { value: '', label: t('orders.wallet.filter.all') },
              ...ACCOUNTS.map((value) => ({ value, label: t(`orders.wallet.filter.${value}`) })),
            ]}
            onChange={(next) => setAccount(ACCOUNTS.find((value) => value === next) ?? '')}
            className="h-10 w-auto min-w-44 text-qb-caption"
          />
        </div>
      }
    >
      {query.isPending ? (
        <PageState kind="loading" />
      ) : query.isError ? (
        <PageState kind="error" onRetry={() => query.refetch()} />
      ) : entries.length === 0 ? (
        <EmptyState
          icon={<Icon icon={Receipt} size="lg" />}
          title={t('orders.wallet.empty')}
          description={t('orders.wallet.empty_body')}
          headingLevel="h3"
          className="border-t border-qb-line"
        />
      ) : (
        <>
          <table className={tc.table} aria-labelledby="wallet-history">
            <thead>
              <tr className={tc.headRow}>
                <Th>{t('orders.wallet.columns.transaction')}</Th>
                <Th className={tc.wide}>{t('orders.wallet.columns.date')}</Th>
                <Th className="hidden qb-desktop:table-cell">{t('orders.wallet.columns.account')}</Th>
                <Th className="text-end">{t('orders.wallet.columns.amount')}</Th>
                <Th className="hidden text-end qb-desktop:table-cell">{t('orders.wallet.columns.balance')}</Th>
              </tr>
            </thead>
            <tbody>
              {entries.map((entry) => (
                <StatementRow key={entry.id} entry={entry} />
              ))}
            </tbody>
          </table>
          {query.hasNextPage ? <LoadMore onClick={() => void query.fetchNextPage()} loading={query.isFetchingNextPage} /> : null}
        </>
      )}
    </TableCard>
  );
}

function StatementRow({ entry }: { entry: WalletEntry }) {
  const date = (
    <time dateTime={isoDate(entry.posted_at)} className="whitespace-nowrap">
      {formatDate(entry.posted_at)}
    </time>
  );
  const reference = entry.reference;

  return (
    <tr className={tc.row}>
      <td className={tc.td}>
        <p className="font-semibold text-qb-ink-title">{t(`orders.wallet.type.${entry.type}`, entry.description)}</p>
        <p className="mt-0.5 text-qb-micro font-normal text-qb-ink-subtle qb-desktop:text-qb-caption">
          {reference?.type === 'order' ? (
            <Link
              href={`/account/orders/${encodeURIComponent(reference.id)}`}
              className={cn('rounded-qb-xs underline underline-offset-2 hover:text-qb-ink', focusRing)}
            >
              {t('orders.common.order_number', { number: orderNumber(reference.id) })}
            </Link>
          ) : reference ? (
            t(`orders.wallet.reference.${reference.type}`)
          ) : null}
          <span className="qb-desktop:hidden">
            {reference ? ' · ' : null}
            {t(`orders.wallet.account.${entry.account}`)}
          </span>
          <span className="qb-tablet:hidden"> · {date}</span>
        </p>
      </td>
      <td className={cn(tc.td, tc.wide)}>{date}</td>
      <td className={cn(tc.td, 'hidden qb-desktop:table-cell')}>{t(`orders.wallet.account.${entry.account}`)}</td>
      <td className={cn(tc.td, tc.amount)}>
        <span dir="ltr" className={entry.account === 'wallet' && entry.direction === 'increase' ? 'text-qb-success' : undefined}>
          {signedAmount(entry)}
        </span>
      </td>
      <td className={cn(tc.td, tc.amount, 'hidden font-medium text-qb-ink-secondary qb-desktop:table-cell')}>
        {formatMoney(entry.balance_after, entry.currency)}
      </td>
    </tr>
  );
}
