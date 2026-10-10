'use client';

import { useRef, useState, type ChangeEvent, type FormEvent } from 'react';
import { Landmark, Upload, Wallet as WalletIcon } from 'lucide-react';
import { toast } from 'sonner';

import { Button, buttonVariants } from '@/components/design-system/Button';
import { Field } from '@/components/design-system/Field';
import { Input } from '@/components/design-system/Input';
import { Notice } from '@/components/design-system/Notice';
import { RadioCard } from '@/components/design-system/RadioCard';
import { AccountPageFrame } from '@/components/orders/AccountPageFrame';
import { AmountField } from '@/components/orders/AmountField';
import { focusFirstInvalid } from '@/components/orders/focus-invalid';
import { FormError } from '@/components/orders/NoteField';
import { PageState } from '@/components/orders/PageState';
import { StatusPill } from '@/components/orders/StatusPill';
import { LoadMore, TableCard, Th, tableClasses as tc } from '@/components/orders/TableCard';
import { useIdempotencyKey } from '@/hooks/useIdempotencyKey';
import type { Settlement, SettlementPayload, Wallet } from '@/lib/api/commerce-types';
import { t } from '@/lib/i18n/messages';
import { formatDate, isoDate } from '@/lib/orders/dates';
import { dealErrorMessage, fieldErrors, isHandledGlobally } from '@/lib/orders/errors';
import { formatMoney, isPositiveAmount, minAmount, remainingAmount } from '@/lib/orders/money';
import { REVIEW_TONE } from '@/lib/orders/status';
import { isolate } from '@/lib/orders/text';
import { LIMITS, validateAmount, validateText } from '@/lib/orders/validation';
import { useCreateSettlementMutation, useSettlementsQuery, useWalletQuery } from '@/lib/queries/wallet';
import { cn } from '@/lib/utils';

import { walletTrail } from './wallet-trail';

const RECEIPT_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

type Method = SettlementPayload['method'];

/** `/account/wallet/settlements`: pay the commission owed by bank transfer (reference + receipt) or from the wallet. */
export function SettlementView() {
  const wallet = useWalletQuery();
  const settlements = useSettlementsQuery();
  const pendingTransfer =
    settlements.data?.pages
      .flatMap((page) => page.data)
      .find((settlement) => settlement.method === 'bank_transfer' && settlement.status === 'pending') ?? null;

  return (
    <AccountPageFrame
      breadcrumb={walletTrail(t('orders.settlement.title'))}
      title={t('orders.settlement.title')}
      description={t('orders.settlement.subtitle')}
    >
      {wallet.isPending || settlements.isPending ? (
        <PageState kind="loading" />
      ) : wallet.isError ? (
        <PageState kind="error" onRetry={() => wallet.refetch()} />
      ) : (
        <SettlementForm
          // A settlement changes the debt and the balance; the form starts over from the new figures.
          key={[wallet.data.commission_debt, wallet.data.available_balance, pendingTransfer?.id ?? ''].join(':')}
          wallet={wallet.data}
          pendingTransfer={pendingTransfer}
        />
      )}
      <SettlementsTable />
    </AccountPageFrame>
  );
}

interface FormErrors {
  amount?: string;
  reference?: string;
  receipt?: string;
  form?: string;
}

function SettlementForm({ wallet, pendingTransfer }: { wallet: Wallet; pendingTransfer: Settlement | null }) {
  const create = useCreateSettlementMutation();
  const { key, renew } = useIdempotencyKey();
  const formRef = useRef<HTMLFormElement>(null);
  const fileRef = useRef<HTMLInputElement>(null);
  const owed = wallet.commission_debt;
  // The API does not let the wallet pay debt that a transfer awaiting review already covers.
  const walletCap = minAmount(remainingAmount(owed, pendingTransfer?.amount ?? '0.00'), wallet.available_balance);
  const canUseWallet = isPositiveAmount(walletCap);
  const canTransfer = pendingTransfer === null;
  const initialMethod: Method = canTransfer || !canUseWallet ? 'bank_transfer' : 'wallet';

  const [method, setMethod] = useState<Method>(initialMethod);
  const [amount, setAmount] = useState(initialMethod === 'wallet' ? walletCap : owed);
  const [reference, setReference] = useState('');
  const [receipt, setReceipt] = useState<File | null>(null);
  const [errors, setErrors] = useState<FormErrors>({});

  if (!isPositiveAmount(owed)) {
    return (
      <Notice tone="success" role="status">
        {t('orders.settlement.nothing_owed')}
      </Notice>
    );
  }

  const max = method === 'wallet' ? walletCap : owed;

  const onReceipt = (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0] ?? null;
    setReceipt(file);
    setErrors((current) => ({ ...current, receipt: undefined }));
  };

  const receiptProblem = (): string | null => {
    if (!receipt) return t('orders.settlement.receipt_required');
    if (!RECEIPT_TYPES.includes(receipt.type)) return t('orders.settlement.receipt_type');
    if (receipt.size > LIMITS.proofMaxBytes) return t('orders.settlement.receipt_size');
    return null;
  };

  const check = (): { payload: SettlementPayload | null; errors: FormErrors } => {
    const next: FormErrors = {};
    const checked = validateAmount(amount, { min: '0.01', max, currency: wallet.currency });
    if (checked.amount === null) next.amount = checked.error;
    if (method === 'bank_transfer') {
      const referenceError = validateText(reference, { max: LIMITS.bankReferenceMax, required: true });
      if (referenceError) next.reference = referenceError;
      const receiptError = receiptProblem();
      if (receiptError) next.receipt = receiptError;
    }
    if (checked.amount === null || Object.keys(next).length > 0) return { payload: null, errors: next };
    if (method === 'wallet') return { payload: { method: 'wallet', amount: checked.amount }, errors: next };
    if (!receipt) return { payload: null, errors: next };
    return { payload: { method: 'bank_transfer', amount: checked.amount, bankReference: reference.trim(), proof: receipt }, errors: next };
  };

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    const { payload, errors: next } = check();
    setErrors(next);
    if (!payload) {
      focusFirstInvalid(formRef.current);
      return;
    }
    try {
      await create.mutateAsync({ payload, idempotencyKey: key });
      renew();
      setReference('');
      setReceipt(null);
      if (fileRef.current) fileRef.current.value = '';
      toast.success(t(payload.method === 'wallet' ? 'orders.settlement.toast_wallet' : 'orders.settlement.toast_bank'));
    } catch (error) {
      if (isHandledGlobally(error)) return;
      const fields = fieldErrors(error);
      const failed: FormErrors = { amount: fields.amount, reference: fields.bank_reference, receipt: fields.proof };
      if (!failed.amount && !failed.reference && !failed.receipt) failed.form = dealErrorMessage(error);
      setErrors(failed);
      focusFirstInvalid(formRef.current);
    }
  };

  return (
    // The same card and heading as "Your commission payments" under it.
    <TableCard padded title={t('orders.settlement.owed', { amount: formatMoney(owed, wallet.currency) })} titleId="settle-form">
      {pendingTransfer ? (
        <Notice tone="brand" role="status" className="mb-4">
          {t('orders.settlement.pending_exists', { amount: formatMoney(pendingTransfer.amount, pendingTransfer.currency) })}
        </Notice>
      ) : null}
      <form ref={formRef} onSubmit={submit} noValidate className="flex flex-col gap-5">
        <div role="radiogroup" aria-labelledby="settle-method" className="flex flex-col gap-3">
          <p id="settle-method" className="text-qb-body text-qb-ink-body">
            {t('orders.settlement.method')}
          </p>
          <div className="[display:grid] grid-cols-1 gap-3 qb-desktop:grid-cols-2 qb-desktop:gap-5">
            <RadioCard
              name="settlement_method"
              value="bank_transfer"
              checked={method === 'bank_transfer'}
              disabled={!canTransfer}
              onChange={() => setMethod('bank_transfer')}
              icon={<Landmark />}
              label={t('orders.settlement.bank_transfer')}
              description={t('orders.settlement.bank_transfer_hint')}
            />
            <RadioCard
              name="settlement_method"
              value="wallet"
              checked={method === 'wallet'}
              disabled={!canUseWallet}
              onChange={() => {
                setMethod('wallet');
                setAmount(walletCap);
              }}
              icon={<WalletIcon />}
              label={t('orders.settlement.wallet')}
              description={
                canUseWallet
                  ? t('orders.settlement.wallet_hint', { available: formatMoney(wallet.available_balance, wallet.currency) })
                  : t('orders.settlement.wallet_empty')
              }
            />
          </div>
        </div>

        {method === 'bank_transfer' && !canTransfer ? null : (
          <>
            <AmountField
              label={t('orders.settlement.amount')}
              value={amount}
              onChange={setAmount}
              error={errors.amount}
              hint={t('orders.settlement.amount_hint', { max: formatMoney(max, wallet.currency) })}
              currency={wallet.currency}
              required
              className="max-w-md"
            />
            {method === 'bank_transfer' ? (
              <>
                <Field label={t('orders.settlement.reference')} error={errors.reference} required className="max-w-md">
                  {(control) => (
                    <Input
                      {...control}
                      dir="ltr"
                      autoComplete="off"
                      maxLength={LIMITS.bankReferenceMax}
                      placeholder={t('orders.settlement.reference_placeholder')}
                      value={reference}
                      onChange={(event) => setReference(event.target.value)}
                    />
                  )}
                </Field>
                <Field
                  label={t('orders.settlement.receipt')}
                  hint={receipt ? t('orders.settlement.receipt_selected', { name: receipt.name }) : t('orders.settlement.receipt_hint')}
                  error={errors.receipt}
                  required
                >
                  {(control) => (
                    // The browser's own file button speaks the browser's language, so it is replaced by a translated one.
                    <div className="flex">
                      <input
                        {...control}
                        ref={fileRef}
                        type="file"
                        accept={RECEIPT_TYPES.join(',')}
                        onChange={onReceipt}
                        className="peer sr-only"
                      />
                      <label
                        htmlFor={control.id}
                        className={cn(
                          buttonVariants({ variant: 'secondary', size: 'sm' }),
                          'rounded-qb-sm peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-solid peer-focus-visible:outline-qb-brand-active peer-aria-invalid:border-qb-danger',
                        )}
                      >
                        <Upload aria-hidden="true" />
                        {t(receipt ? 'orders.settlement.change_file' : 'orders.settlement.choose_file')}
                      </label>
                    </div>
                  )}
                </Field>
              </>
            ) : null}
            <FormError>{errors.form}</FormError>
            <Button type="submit" className="h-11 self-start rounded-qb-sm" disabled={create.isPending} aria-busy={create.isPending}>
              {t('orders.settlement.submit')}
            </Button>
          </>
        )}
      </form>
    </TableCard>
  );
}

function SettlementsTable() {
  const query = useSettlementsQuery();
  const rows = query.data?.pages.flatMap((page) => page.data) ?? [];

  return (
    <TableCard title={t('orders.settlement.history_title')} titleId="settlements">
      {query.isPending ? (
        <PageState kind="loading" />
      ) : query.isError ? (
        <PageState kind="error" onRetry={() => query.refetch()} />
      ) : rows.length === 0 ? (
        <p className="border-t border-qb-line px-5 py-8 text-center text-qb-body text-qb-ink-secondary qb-desktop:px-8">
          {t('orders.settlement.empty')}
        </p>
      ) : (
        <>
          <table className={tc.table} aria-labelledby="settlements">
            <thead>
              <tr className={tc.headRow}>
                <Th>{t('orders.settlement.columns.method')}</Th>
                <Th className={tc.wide}>{t('orders.settlement.columns.date')}</Th>
                <Th className={tc.wide}>{t('orders.settlement.columns.status')}</Th>
                <Th className="text-end">{t('orders.settlement.columns.amount')}</Th>
              </tr>
            </thead>
            <tbody>
              {rows.map((settlement) => (
                <SettlementRow key={settlement.id} settlement={settlement} />
              ))}
            </tbody>
          </table>
          {query.hasNextPage ? <LoadMore onClick={() => void query.fetchNextPage()} loading={query.isFetchingNextPage} /> : null}
        </>
      )}
    </TableCard>
  );
}

function SettlementRow({ settlement }: { settlement: Settlement }) {
  const tone = REVIEW_TONE[settlement.status];
  const label = t(`orders.status.review.${settlement.status}`);
  const date = (
    <time dateTime={isoDate(settlement.created_at)} className="whitespace-nowrap">
      {formatDate(settlement.created_at)}
    </time>
  );

  return (
    <tr className={tc.row}>
      <td className={tc.td}>
        <p className="font-semibold text-qb-ink-title">
          {t(settlement.method === 'wallet' ? 'orders.settlement.wallet' : 'orders.settlement.bank_transfer')}
        </p>
        <p className="mt-0.5 text-qb-micro font-normal text-qb-ink-subtle qb-desktop:text-qb-caption">
          {settlement.bank_reference ? <span dir="ltr">{settlement.bank_reference}</span> : null}
          <span className="qb-tablet:hidden">
            {settlement.bank_reference ? ' · ' : null}
            {date}
          </span>
        </p>
        {settlement.rejection_reason ? (
          <p className="mt-1 text-qb-micro font-normal text-qb-danger qb-desktop:text-qb-caption">
            {t('orders.settlement.rejected_reason', { reason: isolate(settlement.rejection_reason) })}
          </p>
        ) : null}
      </td>
      <td className={cn(tc.td, tc.wide)}>{date}</td>
      <td className={cn(tc.td, tc.wide)}>
        <StatusPill tone={tone}>{label}</StatusPill>
      </td>
      <td className={cn(tc.td, tc.amount)}>
        {formatMoney(settlement.amount, settlement.currency)}
        <div className="mt-1 qb-tablet:hidden">
          <StatusPill tone={tone} compact>
            {label}
          </StatusPill>
        </div>
      </td>
    </tr>
  );
}
