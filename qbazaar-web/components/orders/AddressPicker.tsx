'use client';

import { useState } from 'react';
import { MapPin, Plus } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { Field } from '@/components/design-system/Field';
import { focusRing } from '@/components/design-system/focus-ring';
import { Input } from '@/components/design-system/Input';
import type { CheckoutAddressInput, DeliveryAddress, SavedAddress } from '@/lib/api/commerce-types';
import { t } from '@/lib/i18n/messages';
import { addressLines, toAddressPayload, type AddressField } from '@/lib/orders/address';
import { cn } from '@/lib/utils';

import { CheckoutPanel } from './CheckoutPanel';
import { OptionTile } from './OptionTile';

import '@/styles/design-tokens-sell.css';

export type AddressChoice = { kind: 'saved'; id: string } | { kind: 'new' };

export interface ShippingAddressPanelProps {
  addresses: SavedAddress[];
  choice: AddressChoice;
  onChoiceChange: (choice: AddressChoice) => void;
  address: CheckoutAddressInput;
  onAddressChange: (address: CheckoutAddressInput) => void;
  errors: Partial<Record<AddressField, string>>;
  /** Checks the typed address and shows its errors; true when it can be used. */
  onCheckAddress: () => boolean;
}

/** The form of 689:33725 on six columns: two fields a row, then street and number, then three. */
const FIELDS: Array<{ field: AddressField; autoComplete: string; span: string; required?: boolean; type?: string }> = [
  { field: 'full_name', autoComplete: 'name', span: 'qb-tablet:col-span-3', required: true },
  { field: 'phone', autoComplete: 'tel', span: 'qb-tablet:col-span-3', type: 'tel' },
  { field: 'street', autoComplete: 'address-line1', span: 'qb-tablet:col-span-4', required: true },
  { field: 'house_number', autoComplete: 'address-line2', span: 'qb-tablet:col-span-2', required: true },
  { field: 'city', autoComplete: 'address-level2', span: 'qb-tablet:col-span-2', required: true },
  { field: 'postal_code', autoComplete: 'postal-code', span: 'qb-tablet:col-span-2' },
  { field: 'supplement', autoComplete: 'address-line3', span: 'qb-tablet:col-span-2' },
];

const headerLink = cn('rounded-qb-xs text-qb-body font-medium text-qb-brand hover:underline', focusRing);

/**
 * "Shipping Address" of the checkout (682:32513, 689:33725): the address the
 * order goes to as text with an Edit link; editing turns the link into
 * Cancel and opens the saved addresses and the address form with a Save
 * Address button. Without a saved or confirmed address the form is open.
 */
export function ShippingAddressPanel({ addresses, choice, onChoiceChange, address, onAddressChange, errors, onCheckAddress }: ShippingAddressPanelProps) {
  const [editingFrom, setEditingFrom] = useState<AddressChoice | null>(null);
  const [typedConfirmed, setTypedConfirmed] = useState(false);
  const saved = choice.kind === 'saved' ? addresses.find((candidate) => candidate.id === choice.id) : undefined;
  const confirmed = saved !== undefined || typedConfirmed;
  const editing = editingFrom !== null || !confirmed || Object.keys(errors).length > 0;

  const startEditing = () => setEditingFrom(choice);
  const cancel = () => {
    if (editingFrom) onChoiceChange(editingFrom);
    setEditingFrom(null);
  };
  const save = () => {
    if (choice.kind === 'new') {
      if (!onCheckAddress()) return;
      setTypedConfirmed(true);
    }
    setEditingFrom(null);
  };

  const action = editing ? (
    editingFrom ? (
      <button type="button" onClick={cancel} className={headerLink}>
        {t('common.cancel')}
      </button>
    ) : null
  ) : (
    <button type="button" onClick={startEditing} className={headerLink}>
      {t('orders.checkout.edit_address')}
    </button>
  );

  return (
    <CheckoutPanel title={t('orders.checkout.address_title')} titleId="checkout-address" action={action}>
      {editing ? (
        <div className="flex flex-col gap-4">
          {addresses.length > 0 ? (
            <div role="radiogroup" aria-labelledby="checkout-address" className="flex flex-col gap-3">
              {addresses.map((option) => (
                <OptionTile
                  key={option.id}
                  name="address"
                  value={option.id}
                  checked={choice.kind === 'saved' && choice.id === option.id}
                  onChange={() => onChoiceChange({ kind: 'saved', id: option.id })}
                  icon={<MapPin />}
                  label={savedAddressTitle(option)}
                  details={addressLines(option).map((line) => (
                    <bdi key={line} className="block">
                      {line}
                    </bdi>
                  ))}
                />
              ))}
              <OptionTile
                name="address"
                value="new"
                checked={choice.kind === 'new'}
                onChange={() => onChoiceChange({ kind: 'new' })}
                icon={<Plus />}
                label={t('orders.checkout.new_address')}
              />
            </div>
          ) : null}
          {choice.kind === 'new' ? <AddressForm address={address} onAddressChange={onAddressChange} errors={errors} /> : null}
          <Button size="sm" onClick={save} className="h-10 self-end rounded-qb-md px-[18px] text-qb-caption">
            {t('orders.checkout.save_address')}
          </Button>
        </div>
      ) : (
        <AddressText address={saved ?? toAddressPayload(address)} />
      )}
    </CheckoutPanel>
  );
}

function savedAddressTitle(address: SavedAddress) {
  return (
    <span className="flex flex-wrap items-center gap-2">
      <span>
        {address.label ? (
          <>
            <bdi>{address.label}</bdi> ·{' '}
          </>
        ) : null}
        <bdi>{address.full_name}</bdi>
      </span>
      {address.is_default ? (
        <span className="rounded-qb-xs bg-qb-fill px-2 py-0.5 text-qb-micro font-normal text-qb-ink-secondary">{t('orders.checkout.default_address')}</span>
      ) : null}
    </span>
  );
}

/** The chosen address as checkout.html prints it: the name in bold, then a line per part. */
function AddressText({ address }: { address: DeliveryAddress }) {
  return (
    <address className="text-qb-body-sm leading-[1.9] text-qb-ink-body not-italic">
      <bdi className="block font-semibold text-qb-ink">{address.full_name}</bdi>
      {addressLines(address).map((line) => (
        <bdi key={line} className="block">
          {line}
        </bdi>
      ))}
    </address>
  );
}

function AddressForm({
  address,
  onAddressChange,
  errors,
}: {
  address: CheckoutAddressInput;
  onAddressChange: (address: CheckoutAddressInput) => void;
  errors: Partial<Record<AddressField, string>>;
}) {
  return (
    <div className="[display:grid] grid-cols-1 gap-4 qb-tablet:grid-cols-6">
      {FIELDS.map(({ field, autoComplete, span, required, type }) => (
        <Field
          key={field}
          label={
            <>
              {t(`orders.checkout.fields.${field}`)}
              {/* The form's stars are red (689:33725), so the field draws its own. */}
              {required ? (
                <span aria-hidden="true" className="text-(--color-qb-required)">
                  {' *'}
                </span>
              ) : null}
            </>
          }
          error={errors[field]}
          className={cn(span, '[&>label]:text-qb-body-sm')}
        >
          {(control) => (
            <Input
              {...control}
              required={required}
              type={type ?? 'text'}
              autoComplete={autoComplete}
              placeholder={t(`orders.checkout.placeholders.${field}`)}
              value={address[field] ?? ''}
              onChange={(event) => onAddressChange({ ...address, [field]: event.target.value })}
              className="h-11 rounded-qb-md text-qb-caption"
            />
          )}
        </Field>
      ))}
    </div>
  );
}
