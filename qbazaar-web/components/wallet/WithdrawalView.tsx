'use client';

import { useRef, useState, type FormEvent } from 'react';
import Link from 'next/link';
import { Landmark } from 'lucide-react';
import { toast } from 'sonner';

import { Button, buttonVariants } from '@/components/design-system/Button';
import { Notice } from '@/components/design-system/Notice';
import { RadioCard } from '@/components/design-system/RadioCard';
import { AccountPageHeader } from '@/components/orders/AccountPageHeader';
import { AmountField } from '@/components/orders/AmountField';
import { CheckoutPanel } from '@/components/orders/CheckoutPanel';
import { focusFirstInvalid } from '@/components/orders/focus-invalid';
import { FormError } from '@/components/orders/NoteField';
import { PageState } from '@/components/orders/PageState';
import { StatusPill } from '@/components/orders/StatusPill';
import { LoadMore, TableCard, tableClasses as tc } from '@/components/orders/TableCard';
import { useIdempotencyKey } from '@/hooks/useIdempotencyKey';
import { ApiClientError } from '@/lib/api/auth';
import type { BankAccount, Wallet, Withdrawal } from '@/lib/api/commerce-types';
import { errorDetail } from '@/lib/api/request';
import { t } from '@/lib/i18n/messages';
import { formatDate, isoDate } from '@/lib/orders/dates';
import { dealErrorMessage, fieldErrors, isHandledGlobally } from '@/lib/orders/errors';
import { formatMoney, isPositiveAmount, normalizeAmountInput } from '@/lib/orders/money';
import { REVIEW_TONE } from '@/lib/orders/status';
import { validateAmount } from '@/lib/orders/validation';
import { useBankAccountsQuery, useCreateWithdrawalMutation, useWalletQuery, useWithdrawalsQuery } from '@/lib/queries/wallet';
import { cn } from '@/lib/utils';

import { walletTrail } from './wallet-trail';

/** `/account/wallet/withdrawals`: send the withdrawable balance to a payout IBAN and follow the requests. */
export function WithdrawalView() {
  const wallet = useWalletQuery();
  const accounts = useBankAccountsQuery();

  return (
    <div className="flex flex-col gap-6 font-qb">
      <AccountPageHeader
        title={t('orders.withdrawal.title')}
        description={t('orders.withdrawal.subtitle')}
        breadcrumb={walletTrail(t('orders.withdrawal.title'))}
      />
      {wallet.isPending || accounts.isPending ? (
        <PageState kind="loading" />
      ) : wallet.isError || accounts.isError ? (
        <PageState
          kind="error"
          onRetry={() => {
            void wallet.refetch();
            void accounts.refetch();
          }}
        />
      ) : (
        <WithdrawalForm wallet={wallet.data} accounts={accounts.data} />
      )}
      <WithdrawalsTable />
    </div>
  );
}

interface FormErrors {
  amount?: string;
  account?: string;
  form?: string;
}

function WithdrawalForm({ wallet, accounts }: { wallet: Wallet; accounts: BankAccount[] }) {
  const create = useCreateWithdrawalMutation();
  const { key, renew } = useIdempotencyKey();
  const formRef = useRef<HTMLFormElement>(null);
  const [amount, setAmount] = useState('');
  const [accountId, setAccountId] = useState(() => (accounts.find((account) => account.is_default) ?? accounts[0])?.id ?? '');
  const [errors, setErrors] = useState<FormErrors>({});

  const withdrawable = wallet.withdrawable_balance;
  const canWithdraw = isPositiveAmount(withdrawable);

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    const checked = validateAmount(amount, { min: '0.01', max: withdrawable, currency: wallet.currency });
    if (checked.amount === null) {
      setErrors({ amount: checked.error });
      focusFirstInvalid(formRef.current);
      return;
    }
    try {
      await create.mutateAsync({ payload: { amount: checked.amount, bank_account_id: accountId || null }, idempotencyKey: key });
      renew();
      setAmount('');
      setErrors({});
      toast.success(t('orders.withdrawal.toast'));
    } catch (error) {
      if (isHandledGlobally(error)) return;
      const cap = error instanceof ApiClientError && error.code === 'WALLET_003' ? errorDetail(error, 'withdrawable') : null;
      if (cap) {
        setErrors({ amount: t('orders.withdrawal.cap', { amount: formatMoney(normalizeAmountInput(cap) ?? cap, wallet.currency) }) });
        focusFirstInvalid(formRef.current);
        return;
      }
      const fields = fieldErrors(error);
      const next: FormErrors = { amount: fields.amount, account: fields.bank_account_id };
      if (!next.amount && !next.account) next.form = dealErrorMessage(error);
      setErrors(next);
      focusFirstInvalid(formRef.current);
    }
  };

  return (
    <CheckoutPanel title={t('orders.withdrawal.available', { amount: formatMoney(withdrawable, wallet.currency) })} titleId="withdraw-form">
      {isPositiveAmount(wallet.commission_debt) ? (
        <p className="mb-4 text-qb-caption text-qb-ink-secondary">
          {t('orders.withdrawal.debt_note', { debt: formatMoney(wallet.commission_debt, wallet.currency) })}
        </p>
      ) : null}
      {!canWithdraw ? (
        <Notice tone="neutral" role="status">
          {t('orders.withdrawal.nothing')}
        </Notice>
      ) : accounts.length === 0 ? (
        <Notice tone="info" role="status">
          <p>{t('orders.withdrawal.no_accounts')}</p>
          <Link href="/account/wallet/bank-accounts" className={cn(buttonVariants({ size: 'sm' }), 'mt-3 rounded-qb-sm')}>
            {t('orders.withdrawal.add_account')}
          </Link>
        </Notice>
      ) : (
        <form ref={formRef} onSubmit={submit} noValidate className="flex flex-col gap-4">
          <AmountField
            label={t('orders.withdrawal.amount')}
            value={amount}
            onChange={setAmount}
            error={errors.amount}
            hint={t('orders.settlement.amount_hint', { max: formatMoney(withdrawable, wallet.currency) })}
            currency={wallet.currency}
            required
            className="max-w-md"
          />
          <div role="radiogroup" aria-labelledby="withdraw-account" className="flex flex-col gap-3">
            <p id="withdraw-account" className="text-qb-body text-qb-ink-body">
              {t('orders.withdrawal.account')}
            </p>
            {accounts.map((account) => (
              <RadioCard
                key={account.id}
                name="bank_account"
                value={account.id}
                checked={accountId === account.id}
                onChange={() => setAccountId(account.id)}
                icon={<Landmark />}
                label={account.holder_name}
                description={
                  <>
                    <span dir="ltr">{account.iban_masked}</span>
                    {account.bank_name ? ` · ${account.bank_name}` : null}
                  </>
                }
              />
            ))}
            {errors.account ? <p className="text-qb-caption text-qb-danger">{errors.account}</p> : null}
            <Link href="/account/wallet/bank-accounts" className="self-start text-qb-caption font-medium text-qb-brand underline underline-offset-2">
              {t('orders.withdrawal.manage_accounts')}
            </Link>
          </div>
          <FormError>{errors.form}</FormError>
          <Button type="submit" className="h-11 self-start rounded-qb-sm" disabled={create.isPending} aria-busy={create.isPending}>
            {t('orders.withdrawal.submit')}
          </Button>
        </form>
      )}
    </CheckoutPanel>
  );
}

function WithdrawalsTable() {
  const query = useWithdrawalsQuery();
  const rows = query.data?.pages.flatMap((page) => page.data) ?? [];

  return (
    <TableCard title={t('orders.withdrawal.history_title')} titleId="withdrawals">
      {query.isPending ? (
        <PageState kind="loading" />
      ) : query.isError ? (
        <PageState kind="error" onRetry={() => query.refetch()} />
      ) : rows.length === 0 ? (
        <p className="border-t border-qb-line px-5 py-8 text-center text-qb-body text-qb-ink-secondary qb-desktop:px-8">
          {t('orders.withdrawal.empty')}
        </p>
      ) : (
        <>
          <table className={tc.table} aria-labelledby="withdrawals">
            <thead>
              <tr className={tc.headRow}>
                <th scope="col" className={tc.th}>
                  {t('orders.withdrawal.columns.account')}
                </th>
                <th scope="col" className={cn(tc.th, tc.wide)}>
                  {t('orders.withdrawal.columns.date')}
                </th>
                <th scope="col" className={cn(tc.th, tc.wide)}>
                  {t('orders.withdrawal.columns.status')}
                </th>
                <th scope="col" className={cn(tc.th, 'text-end')}>
                  {t('orders.withdrawal.columns.amount')}
                </th>
              </tr>
            </thead>
            <tbody>
              {rows.map((withdrawal) => (
                <WithdrawalRow key={withdrawal.id} withdrawal={withdrawal} />
              ))}
            </tbody>
          </table>
          {query.hasNextPage ? <LoadMore onClick={() => void query.fetchNextPage()} loading={query.isFetchingNextPage} /> : null}
        </>
      )}
    </TableCard>
  );
}

function WithdrawalRow({ withdrawal }: { withdrawal: Withdrawal }) {
  const status = <StatusPill tone={REVIEW_TONE[withdrawal.status]}>{t(`orders.status.review.${withdrawal.status}`)}</StatusPill>;
  const date = (
    <time dateTime={isoDate(withdrawal.created_at)} className="whitespace-nowrap">
      {formatDate(withdrawal.created_at)}
    </time>
  );
  const note = withdrawal.rejection_reason
    ? t('orders.withdrawal.rejected_reason', { reason: withdrawal.rejection_reason })
    : withdrawal.transfer_reference
      ? t('orders.withdrawal.transfer_reference', { reference: withdrawal.transfer_reference })
      : null;

  return (
    <tr className={tc.row}>
      <td className={tc.td}>
        <p className="font-semibold text-qb-ink-title">{withdrawal.holder_name}</p>
        <p className="mt-0.5 text-qb-micro font-normal text-qb-ink-subtle qb-desktop:text-qb-caption">
          <span dir="ltr">{withdrawal.iban_masked}</span>
          <span className="qb-tablet:hidden"> · {date}</span>
        </p>
        {note ? <p className="mt-1 text-qb-micro font-normal text-qb-ink-secondary qb-desktop:text-qb-caption">{note}</p> : null}
      </td>
      <td className={cn(tc.td, tc.wide)}>{date}</td>
      <td className={cn(tc.td, tc.wide)}>{status}</td>
      <td className={cn(tc.td, tc.amount)}>
        {formatMoney(withdrawal.amount, withdrawal.currency)}
        <div className="mt-1 qb-tablet:hidden">{status}</div>
      </td>
    </tr>
  );
}
