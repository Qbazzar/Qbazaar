'use client';

import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Loader2, Pencil, Plus, Trash2 } from 'lucide-react';

import { Field } from '@/components/design-system/Field';
import { focusRing } from '@/components/design-system/focus-ring';
import { Input } from '@/components/design-system/Input';
import { fieldErrorText } from '@/components/auth/FieldError';
import { ApiClientError } from '@/lib/api/auth';
import { createAddress, deleteAddress, updateAddress } from '@/lib/api/account';
import type { SavedAddress } from '@/lib/api/commerce-types';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import { apiErrorMessage } from './api-error-message';
import { addressSchema, type AddressFormValues, type AddressInput } from '@/lib/validation/account';

import {
  AccountDialog,
  AccountDialogActions,
  accountCancelClass,
  accountInputClass,
  accountSaveClass,
} from './AccountDialog';

export const ADDRESSES_QUERY_KEY = ['account', 'addresses'] as const;

/** "Al Sadd Street 12, Doha": the one-line address of the Delivery Address row. */
export function addressSummary(address: SavedAddress): string {
  return `${address.street} ${address.house_number}, ${address.city}`;
}

type View = { kind: 'list' } | { kind: 'form'; address: SavedAddress | null };

interface AddressDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  addresses: SavedAddress[];
  /** Pre-fills a new address with the account name. */
  defaultName: string;
}

/**
 * Delivery address dialogs of account.html: "Add Address" while there is
 * none, otherwise the saved list ("Edit Address") with edit and delete per
 * address and "Add Address" under it (401:11942, 401:12073, 401:12401).
 */
export function AddressDialog({ open, onOpenChange, addresses, defaultName }: AddressDialogProps) {
  const [view, setView] = useState<View>({ kind: 'list' });
  const showForm = view.kind === 'form' || addresses.length === 0;
  const editing = view.kind === 'form' ? view.address : null;

  const close = (next: boolean) => {
    onOpenChange(next);
    if (!next) setView({ kind: 'list' });
  };

  const title = showForm && !editing ? t('account.address.add_title') : t('account.address.edit_title');

  return (
    <AccountDialog open={open} onOpenChange={close} title={title}>
      {showForm ? (
        <AddressForm
          key={editing?.id ?? 'new'}
          address={editing}
          defaultName={defaultName}
          onDone={() => (addresses.length === 0 && !editing ? close(false) : setView({ kind: 'list' }))}
          onCancel={() => (addresses.length === 0 ? close(false) : setView({ kind: 'list' }))}
        />
      ) : (
        <AddressList
          addresses={addresses}
          onEdit={(address) => setView({ kind: 'form', address })}
          onAdd={() => setView({ kind: 'form', address: null })}
        />
      )}
    </AccountDialog>
  );
}

const squareButton = cn(
  'flex size-10 cursor-pointer items-center justify-center rounded-qb-md bg-qb-acct-tile disabled:cursor-progress disabled:opacity-60',
  focusRing,
);

function AddressList({
  addresses,
  onEdit,
  onAdd,
}: {
  addresses: SavedAddress[];
  onEdit: (address: SavedAddress) => void;
  onAdd: () => void;
}) {
  const queryClient = useQueryClient();
  const remove = useMutation({
    mutationFn: deleteAddress,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ADDRESSES_QUERY_KEY });
      toast.success(t('account.address.deleted'));
    },
    onError: (err) => toast.error(apiErrorMessage(err)),
  });

  return (
    <>
      <ul className="flex flex-col gap-3">
        {addresses.map((address) => (
          <li
            key={address.id}
            className="flex items-start justify-between gap-4 rounded-qb-xl border border-qb-line px-6 py-[22px]"
          >
            <div className="min-w-0 text-qb-body leading-[1.55] text-qb-ink-title">
              <p>{address.full_name}</p>
              <p>{`${address.street} ${address.house_number}`}</p>
              {address.supplement ? <p>{address.supplement}</p> : null}
              <p>{[address.postal_code, address.city].filter(Boolean).join(' ')}</p>
            </div>
            <div className="flex shrink-0 gap-2.5">
              <button
                type="button"
                onClick={() => onEdit(address)}
                aria-label={t('account.address.edit_one', { address: addressSummary(address) })}
                className={cn(squareButton, 'text-qb-ink-muted')}
              >
                <Pencil aria-hidden="true" className="size-[18px]" strokeWidth={1.6} />
              </button>
              <button
                type="button"
                onClick={() => remove.mutate(address.id)}
                disabled={remove.isPending}
                aria-label={t('account.address.delete_one', { address: addressSummary(address) })}
                className={cn(squareButton, 'text-qb-acct-danger')}
              >
                <Trash2 aria-hidden="true" className="size-[18px]" strokeWidth={1.6} />
              </button>
            </div>
          </li>
        ))}
      </ul>
      <div className="mt-[22px] flex justify-end">
        <button
          type="button"
          onClick={onAdd}
          className={cn(accountSaveClass, 'flex-none basis-auto gap-2.5 px-[22px] py-3.5')}
        >
          <Plus aria-hidden="true" />
          {t('account.address.add_title')}
        </button>
      </div>
    </>
  );
}

function AddressForm({
  address,
  defaultName,
  onDone,
  onCancel,
}: {
  address: SavedAddress | null;
  defaultName: string;
  onDone: () => void;
  onCancel: () => void;
}) {
  const queryClient = useQueryClient();
  const form = useForm<AddressFormValues, unknown, AddressInput>({
    resolver: zodResolver(addressSchema),
    mode: 'onBlur',
    defaultValues: {
      full_name: address?.full_name ?? defaultName,
      supplement: address?.supplement ?? '',
      street: address?.street ?? '',
      house_number: address?.house_number ?? '',
      postal_code: address?.postal_code ?? '',
      city: address?.city ?? '',
    },
  });

  const save = useMutation({
    mutationFn: (values: AddressInput) => (address ? updateAddress(address.id, values) : createAddress(values)),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ADDRESSES_QUERY_KEY });
      toast.success(t('account.address.saved'));
      onDone();
    },
  });

  const onSubmit = form.handleSubmit(async (values) => {
    try {
      await save.mutateAsync(values);
    } catch (err) {
      if (!applyFieldErrors(err, form)) toast.error(apiErrorMessage(err));
    }
  });

  const errors = form.formState.errors;
  const field = (name: keyof AddressFormValues, labelKey: string, placeholderKey: string, required = false) => (
    <Field label={t(labelKey)} required={required} error={fieldErrorText(errors[name]?.message)}>
      {(control) => (
        <Input {...control} className={accountInputClass} placeholder={t(placeholderKey)} {...form.register(name)} />
      )}
    </Field>
  );

  return (
    <form onSubmit={onSubmit} noValidate className="flex flex-col gap-[18px] text-start">
      {field('full_name', 'account.address.full_name', 'account.address.full_name_placeholder', true)}
      {field('supplement', 'account.address.supplement', 'account.address.supplement_placeholder')}
      <div className="flex flex-wrap gap-4 *:min-w-0">
        <div className="flex-[1_1_180px]">{field('street', 'account.address.street', 'account.address.street_placeholder', true)}</div>
        <div className="flex-[1_1_120px]">{field('house_number', 'account.address.house_number', 'account.address.house_number_placeholder', true)}</div>
      </div>
      <div className="flex flex-wrap gap-4 *:min-w-0">
        <div className="flex-[1_1_180px]">{field('postal_code', 'account.address.postal_code', 'account.address.postal_code_placeholder')}</div>
        <div className="flex-[1_1_180px]">{field('city', 'account.address.city', 'account.address.city_placeholder', true)}</div>
      </div>
      <AccountDialogActions className="mt-2">
        <button type="submit" disabled={save.isPending} className={accountSaveClass}>
          {save.isPending ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
          {t('common.save')}
        </button>
        <button type="button" onClick={onCancel} disabled={save.isPending} className={accountCancelClass}>
          {t('common.cancel')}
        </button>
      </AccountDialogActions>
    </form>
  );
}

const ADDRESS_FIELDS: readonly (keyof AddressFormValues)[] = [
  'full_name',
  'supplement',
  'street',
  'house_number',
  'postal_code',
  'city',
];

function applyFieldErrors(err: unknown, form: ReturnType<typeof useForm<AddressFormValues, unknown, AddressInput>>): boolean {
  if (!(err instanceof ApiClientError) || !err.details) return false;
  let mapped = false;
  for (const [name, messages] of Object.entries(err.details)) {
    if ((ADDRESS_FIELDS as readonly string[]).includes(name) && messages?.length) {
      form.setError(name as keyof AddressFormValues, { type: 'server', message: messages[0] });
      mapped = true;
    }
  }
  return mapped;
}
