'use client';

import type { ReactNode } from 'react';
import Link from 'next/link';

import { buttonVariants } from '@/components/design-system/Button';
import { focusRing } from '@/components/design-system/focus-ring';
import { CreditCardIcon, WalletIcon, WalletWithdrawIcon, WarningTriangleIcon } from '@/components/design-system/icons';
import { Notice } from '@/components/design-system/Notice';
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

/** `/account/wallet`: balance, commission owed, what can be withdrawn and the statement (wallet.html `.qb-wallet-panel`). */
export function WalletView() {
  const query = useWalletQuery();

  return (
    <div className="flex flex-col font-qb">
      <AccountPageHeader
        className="mb-6"
        title={t('orders.wallet.title')}
        description={t('orders.wallet.subtitle')}
        actions={
          <nav aria-label={t('orders.wallet.actions')} className="flex flex-wrap gap-2">
            <Link href="/account/wallet/bank-accounts" className={cn(buttonVariants({ variant: 'outline' }), headerButton)}>
              <CreditCardIcon aria-hidden="true" />
              <HeaderButtonLabel>{t('orders.wallet.payment_method')}</HeaderButtonLabel>
            </Link>
            {/* Under 1001 px Withdraw Funds moves into the Transaction History card (563:31406, 613:31879). */}
            <Link href="/account/wallet/withdrawals" className={cn(buttonVariants({ variant: 'outline' }), headerButton, 'hidden qb-desktop:inline-flex')}>
              <WalletWithdrawIcon aria-hidden="true" />
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
          <div className="flex flex-wrap gap-5">
            <WalletStat label={t('orders.wallet.balance')} value={formatMoney(query.data.available_balance, query.data.currency)} />
            <WalletStat
              label={t('orders.wallet.commission_owed')}
              value={formatMoney(query.data.commission_debt, query.data.currency)}
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
            <WalletStat
              label={t('orders.wallet.withdrawable')}
              value={formatMoney(query.data.withdrawable_balance, query.data.currency)}
              hint={t('orders.wallet.withdrawable_hint')}
            />
          </div>
        </>
      )}

      <StatementTable
        className="mt-7"
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

/**
 * A figure card of the wallet panel (wallet.html `.qb-wstats`): white, r16 with a 1 px border and
 * the soft card shadow, a 16 px #999 label over the 28 px semibold #333 amount. The cards share a
 * row and stack on phones.
 */
function WalletStat({ label, value, hint }: { label: string; value: string; hint?: ReactNode }) {
  return (
    <div className="min-w-0 flex-1 rounded-qb-xl border border-qb-line bg-qb-surface px-6 py-[22px] shadow-qb-card max-qb-tablet:basis-full">
      <p className="text-qb-body text-qb-ink-meta">{label}</p>
      <p className="mt-1.5 text-qb-h2 font-semibold break-words text-qb-ink-title">{value}</p>
      {hint ? <div className="mt-1 text-qb-caption text-qb-ink-subtle">{hint}</div> : null}
    </div>
  );
}

function DebtNotice({ wallet }: { wallet: Wallet }) {
  const debt = formatMoney(wallet.commission_debt, wallet.currency);
  const ceiling = formatMoney(wallet.debt_ceiling, wallet.currency);

  if (!wallet.can_accept_orders) {
    return (
      <Notice tone="danger" icon={<WarningTriangleIcon aria-hidden="true" />} title={t('orders.wallet.blocked_title')} role="alert" className="mb-5">
        {t('orders.wallet.blocked_body', { debt, ceiling })}
      </Notice>
    );
  }

  const debtCents = toCents(wallet.commission_debt) ?? BigInt(0);
  const ceilingCents = toCents(wallet.debt_ceiling) ?? BigInt(0);
  const nearCeiling = debtCents > BigInt(0) && ceilingCents > BigInt(0) && debtCents * BigInt(100) >= ceilingCents * WARN_AT_PERCENT;

  if (!nearCeiling) return null;
  return (
    <Notice tone="brand" icon={<WarningTriangleIcon aria-hidden="true" />} title={t('orders.wallet.near_title')} className="mb-5">
      {t('orders.wallet.near_body', { debt, ceiling })}
    </Notice>
  );
}
