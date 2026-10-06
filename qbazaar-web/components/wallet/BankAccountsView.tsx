'use client';

import { useRef, useState, type FormEvent } from 'react';
import { Landmark, Trash2 } from 'lucide-react';
import { toast } from 'sonner';

import { Badge } from '@/components/design-system/Badge';
import { Button } from '@/components/design-system/Button';
import { Field } from '@/components/design-system/Field';
import { Input } from '@/components/design-system/Input';
import { AccountPageHeader } from '@/components/orders/AccountPageHeader';
import { CheckoutPanel } from '@/components/orders/CheckoutPanel';
import { ConfirmDialog } from '@/components/orders/ConfirmDialog';
import { focusFirstInvalid } from '@/components/orders/focus-invalid';
import { FormError } from '@/components/orders/NoteField';
import { PageState } from '@/components/orders/PageState';
import type { BankAccount } from '@/lib/api/commerce-types';
import { t } from '@/lib/i18n/messages';
import { dealErrorMessage, fieldErrors, isHandledGlobally } from '@/lib/orders/errors';
import { formatIbanForDisplay, isValidIban, normalizeIban } from '@/lib/orders/iban';
import { LIMITS, validateText } from '@/lib/orders/validation';
import { useAddBankAccountMutation, useBankAccountsQuery, useDeleteBankAccountMutation } from '@/lib/queries/wallet';

import { walletTrail } from './wallet-trail';

/** `/account/wallet/bank-accounts`: payout IBANs, masked, as rows of the payment-method list (504:25438). */
export function BankAccountsView() {
  const query = useBankAccountsQuery();

  return (
    <div className="flex flex-col gap-6 font-qb">
      <AccountPageHeader
        title={t('orders.bank.title')}
        description={t('orders.bank.subtitle')}
        breadcrumb={walletTrail(t('orders.bank.title'))}
      />
      <CheckoutPanel title={t('orders.bank.title')} titleId="bank-accounts">
        {query.isPending ? (
          <PageState kind="loading" />
        ) : query.isError ? (
          <PageState kind="error" onRetry={() => query.refetch()} />
        ) : query.data.length === 0 ? (
          <p className="text-qb-body text-qb-ink-secondary">{t('orders.bank.empty')}</p>
        ) : (
          <ul className="flex flex-col">
            {query.data.map((account) => (
              <BankAccountRow key={account.id} account={account} />
            ))}
          </ul>
        )}
      </CheckoutPanel>
      <AddBankAccountForm isFirst={query.data?.length === 0} />
    </div>
  );
}

function BankAccountRow({ account }: { account: BankAccount }) {
  const remove = useDeleteBankAccountMutation();
  const [confirming, setConfirming] = useState(false);
  const ending = account.iban_masked.replace(/\s/g, '').slice(-4);

  const onRemove = async () => {
    try {
      await remove.mutateAsync(account.id);
      setConfirming(false);
      toast.success(t('orders.bank.toast_removed'));
    } catch (error) {
      if (!isHandledGlobally(error)) toast.error(dealErrorMessage(error));
    }
  };

  return (
    <li className="flex items-center gap-4 border-b border-qb-line py-4 first:pt-0 last:border-b-0 last:pb-0">
      <span
        aria-hidden="true"
        className="flex size-14 shrink-0 items-center justify-center rounded-qb-lg bg-qb-brand-soft text-qb-brand [&_svg]:size-6"
      >
        <Landmark />
      </span>
      <div className="min-w-0 flex-1">
        <p className="flex flex-wrap items-center gap-2 text-qb-body-lg font-medium text-qb-ink">
          <span dir="auto" className="truncate">
            {account.holder_name}
          </span>
          {account.is_default ? (
            <Badge tone="success" size="sm" className="font-qb-label">
              {t('orders.bank.default')}
            </Badge>
          ) : null}
        </p>
        <p className="mt-1 text-qb-caption text-qb-ink-subtle">
          <span dir="ltr" className="font-medium tracking-wide">
            {account.iban_masked}
          </span>
          {account.bank_name ? ` · ${account.bank_name}` : null}
        </p>
      </div>
      <Button
        variant="ghost"
        size="icon"
        aria-label={t('orders.bank.remove_label', { iban: ending })}
        onClick={() => setConfirming(true)}
        className="text-qb-danger hover:bg-qb-danger-soft"
      >
        <Trash2 aria-hidden="true" />
      </Button>
      <ConfirmDialog
        open={confirming}
        onOpenChange={setConfirming}
        title={t('orders.bank.remove_confirm.title')}
        description={t('orders.bank.remove_confirm.description')}
        confirmLabel={t('orders.bank.remove_confirm.confirm')}
        cancelLabel={t('orders.bank.remove_confirm.keep')}
        tone="danger"
        busy={remove.isPending}
        onConfirm={() => void onRemove()}
      />
    </li>
  );
}

interface FormErrors {
  holder?: string;
  iban?: string;
  bankName?: string;
  form?: string;
}

function AddBankAccountForm({ isFirst }: { isFirst: boolean }) {
  const add = useAddBankAccountMutation();
  const formRef = useRef<HTMLFormElement>(null);
  const [holder, setHolder] = useState('');
  const [iban, setIban] = useState('');
  const [bankName, setBankName] = useState('');
  const [makeDefault, setMakeDefault] = useState(false);
  const [errors, setErrors] = useState<FormErrors>({});

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    const next: FormErrors = {};
    if (validateText(holder, { min: LIMITS.holderMin, max: LIMITS.holderMax, required: true })) next.holder = t('orders.bank.holder_error');
    if (!isValidIban(iban)) next.iban = t('orders.bank.iban_error');
    const bankError = validateText(bankName, { max: LIMITS.bankNameMax });
    if (bankError) next.bankName = bankError;
    setErrors(next);
    if (Object.keys(next).length > 0) {
      focusFirstInvalid(formRef.current);
      return;
    }

    try {
      await add.mutateAsync({
        holder_name: holder.trim(),
        iban: normalizeIban(iban),
        bank_name: bankName.trim() || null,
        is_default: isFirst || makeDefault,
      });
      toast.success(t('orders.bank.toast_added'));
      setHolder('');
      setIban('');
      setBankName('');
      setMakeDefault(false);
    } catch (error) {
      if (isHandledGlobally(error)) return;
      const fields = fieldErrors(error);
      const failed: FormErrors = { holder: fields.holder_name, iban: fields.iban, bankName: fields.bank_name };
      if (!failed.holder && !failed.iban && !failed.bankName) failed.form = dealErrorMessage(error);
      setErrors(failed);
      focusFirstInvalid(formRef.current);
    }
  };

  return (
    <CheckoutPanel title={t('orders.bank.add_title')} titleId="bank-add">
      <form ref={formRef} onSubmit={submit} noValidate className="flex flex-col gap-4">
        <div className="[display:grid] grid-cols-1 gap-4 qb-tablet:grid-cols-2">
          <Field label={t('orders.bank.holder')} error={errors.holder} required>
            {(control) => (
              <Input
                {...control}
                autoComplete="name"
                placeholder={t('orders.bank.holder_placeholder')}
                value={holder}
                onChange={(event) => setHolder(event.target.value)}
              />
            )}
          </Field>
          <Field label={t('orders.bank.bank_name')} error={errors.bankName}>
            {(control) => (
              <Input
                {...control}
                autoComplete="organization"
                placeholder={t('orders.bank.bank_placeholder')}
                value={bankName}
                onChange={(event) => setBankName(event.target.value)}
              />
            )}
          </Field>
          <Field label={t('orders.bank.iban')} hint={t('orders.bank.iban_hint')} error={errors.iban} required className="qb-tablet:col-span-2">
            {(control) => (
              <Input
                {...control}
                dir="ltr"
                autoComplete="off"
                autoCapitalize="characters"
                spellCheck={false}
                maxLength={42}
                placeholder={t('orders.bank.iban_placeholder')}
                value={iban}
                onChange={(event) => setIban(event.target.value.toUpperCase())}
                onBlur={() => setIban((value) => formatIbanForDisplay(value))}
                className="font-medium tracking-wide"
              />
            )}
          </Field>
        </div>
        {!isFirst ? (
          <label className="flex items-center gap-3 text-qb-body text-qb-ink-body">
            <input
              type="checkbox"
              checked={makeDefault}
              onChange={(event) => setMakeDefault(event.target.checked)}
              className="size-5 accent-qb-brand"
            />
            {t('orders.bank.make_default')}
          </label>
        ) : null}
        <FormError>{errors.form}</FormError>
        <Button type="submit" className="h-11 self-start rounded-qb-sm" disabled={add.isPending} aria-busy={add.isPending}>
          {t('orders.bank.save')}
        </Button>
      </form>
    </CheckoutPanel>
  );
}
