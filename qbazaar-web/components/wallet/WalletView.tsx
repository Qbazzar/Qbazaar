'use client';

import Link from 'next/link';
import { AlertTriangle, ArrowUpRight, CreditCard, Landmark, Receipt, Wallet as WalletIcon } from 'lucide-react';

import { buttonVariants } from '@/components/design-system/Button';
import { focusRing } from '@/components/design-system/focus-ring';
import { Notice } from '@/components/design-system/Notice';
import { StatTile } from '@/components/design-system/StatTile';
import { AccountPageHeader, HeaderButtonLabel, headerButton } from '@/components/orders/AccountPageHeader';
import { PageState } from '@/components/orders/PageState';
import type { Wallet } from '@/lib/api/commerce-types';
import { t } from '@/lib/i18n/messages';
import { formatMoney, isPositiveAmount, toCents } from '@/lib/orders/money';
import { useWalletQuery } from '@/lib/queries/wallet';
import { cn } from '@/lib/utils';

import { StatementTable } from './StatementTable';

/** Share of the debt ceiling from which the wallet warns before orders are blocked. */
const WARN_AT_PERCENT = BigInt(60);

/** `/account/wallet`: balance, commission owed, what can be withdrawn and the statement (502:22401). */
export function WalletView() {
  const query = useWalletQuery();

  return (
    <div className="flex flex-col gap-6 font-qb">
      <AccountPageHeader
        title={t('orders.wallet.title')}
        description={t('orders.wallet.subtitle')}
        actions={
          <nav aria-label={t('orders.wallet.actions')} className="flex flex-wrap gap-2">
            <Link href="/account/wallet/bank-accounts" className={cn(buttonVariants({ variant: 'outline' }), headerButton)}>
              <CreditCard aria-hidden="true" />
              <HeaderButtonLabel>{t('orders.wallet.payout_accounts')}</HeaderButtonLabel>
            </Link>
            {/* Under 1001 px Withdraw Funds moves into the Transaction History card (563:31406, 613:31879). */}
            <Link href="/account/wallet/withdrawals" className={cn(buttonVariants({ variant: 'outline' }), headerButton, 'hidden qb-desktop:inline-flex')}>
              <ArrowUpRight aria-hidden="true" />
              <HeaderButtonLabel>{t('orders.wallet.withdraw')}</HeaderButtonLabel>
            </Link>
          </nav>
        }
      />

      {query.isPending ? (
        <PageState kind="loading" />
      ) : query.isError ? (
        <PageState kind="error" onRetry={() => query.refetch()} />
      ) : (
        <>
          <DebtNotice wallet={query.data} />
          <div className="[display:grid] grid-cols-2 gap-3 qb-tablet:grid-cols-3 qb-tablet:gap-2 qb-desktop:gap-[13px]">
            <StatTile
              label={t('orders.wallet.balance')}
              value={formatMoney(query.data.available_balance, query.data.currency)}
              icon={<WalletIcon />}
            />
            <StatTile
              label={t('orders.wallet.commission_owed')}
              value={formatMoney(query.data.commission_debt, query.data.currency)}
              icon={<Receipt />}
              tone="info"
              hint={
                <>
                  {t('orders.wallet.ceiling_hint', { ceiling: formatMoney(query.data.debt_ceiling, query.data.currency) })}
                  {isPositiveAmount(query.data.commission_debt) ? (
                    <Link href="/account/wallet/settlements" className={cn('mt-1 block w-fit rounded-qb-xs font-semibold text-qb-brand hover:underline', focusRing)}>
                      {t('orders.wallet.pay_commission')}
                    </Link>
                  ) : null}
                </>
              }
            />
            <StatTile
              label={t('orders.wallet.withdrawable')}
              value={formatMoney(query.data.withdrawable_balance, query.data.currency)}
              icon={<Landmark />}
              hint={t('orders.wallet.withdrawable_hint')}
              className="col-span-2 qb-tablet:col-span-1"
            />
          </div>
        </>
      )}

      <StatementTable
        headerAction={
          <Link
            href="/account/wallet/withdrawals"
            className={cn(buttonVariants({ size: 'sm' }), 'h-8 gap-1.5 rounded-qb-md px-3 text-qb-micro font-normal qb-desktop:hidden [&_svg]:size-4')}
          >
            <WalletIcon aria-hidden="true" />
            {t('orders.wallet.withdraw')}
          </Link>
        }
      />
    </div>
  );
}

function DebtNotice({ wallet }: { wallet: Wallet }) {
  const debt = formatMoney(wallet.commission_debt, wallet.currency);
  const ceiling = formatMoney(wallet.debt_ceiling, wallet.currency);

  if (!wallet.can_accept_orders) {
    return (
      <Notice tone="danger" icon={<AlertTriangle aria-hidden="true" />} title={t('orders.wallet.blocked_title')} role="alert">
        {t('orders.wallet.blocked_body', { debt, ceiling })}
      </Notice>
    );
  }

  const debtCents = toCents(wallet.commission_debt) ?? BigInt(0);
  const ceilingCents = toCents(wallet.debt_ceiling) ?? BigInt(0);
  const nearCeiling = debtCents > BigInt(0) && ceilingCents > BigInt(0) && debtCents * BigInt(100) >= ceilingCents * WARN_AT_PERCENT;

  if (!nearCeiling) return null;
  return (
    <Notice tone="brand" icon={<AlertTriangle aria-hidden="true" />} title={t('orders.wallet.near_title')}>
      {t('orders.wallet.near_body', { debt, ceiling })}
    </Notice>
  );
}
