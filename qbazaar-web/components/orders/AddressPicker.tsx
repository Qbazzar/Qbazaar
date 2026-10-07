'use client';

import { Fragment } from 'react';
import { MapPin, Plus } from 'lucide-react';

import { Field } from '@/components/design-system/Field';
import { Input } from '@/components/design-system/Input';
import { RadioCard } from '@/components/design-system/RadioCard';
import type { CheckoutAddressInput, SavedAddress } from '@/lib/api/commerce-types';
import { t } from '@/lib/i18n/messages';
import { addressLines, type AddressField } from '@/lib/orders/address';
import { cn } from '@/lib/utils';

export type AddressChoice = { kind: 'saved'; id: string } | { kind: 'new' };

export interface AddressPickerProps {
  addresses: SavedAddress[];
  choice: AddressChoice;
  onChoiceChange: (choice: AddressChoice) => void;
  address: CheckoutAddressInput;
  onAddressChange: (address: CheckoutAddressInput) => void;
  errors: Partial<Record<AddressField, string>>;
  labelledBy: string;
}

const FIELDS: Array<{ field: AddressField; autoComplete: string; wide?: boolean; required?: boolean; type?: string }> = [
  { field: 'full_name', autoComplete: 'name', required: true },
  { field: 'phone', autoComplete: 'tel', type: 'tel' },
  { field: 'street', autoComplete: 'address-line1', required: true },
  { field: 'house_number', autoComplete: 'address-line2', required: true },
  { field: 'supplement', autoComplete: 'address-line3', wide: true },
  { field: 'city', autoComplete: 'address-level2', required: true },
  { field: 'postal_code', autoComplete: 'postal-code' },
];

/** Saved delivery addresses to pick from, or a new one typed for this order. */
export function AddressPicker({
  addresses,
  choice,
  onChoiceChange,
  address,
  onAddressChange,
  errors,
  labelledBy,
}: AddressPickerProps) {
  const showForm = choice.kind === 'new';

  return (
    <div className="flex flex-col gap-4">
      {addresses.length > 0 ? (
        <div role="radiogroup" aria-labelledby={labelledBy} className="flex flex-col gap-3">
          {addresses.map((saved) => (
            <RadioCard
              key={saved.id}
              name="address"
              value={saved.id}
              checked={choice.kind === 'saved' && choice.id === saved.id}
              onChange={() => onChoiceChange({ kind: 'saved', id: saved.id })}
              icon={<MapPin />}
              label={
                <span className="flex flex-wrap items-center gap-2">
                  <span>
                    {saved.label ? (
                      <>
                        <bdi>{saved.label}</bdi> ·{' '}
                      </>
                    ) : null}
                    <bdi>{saved.full_name}</bdi>
                  </span>
                  {saved.is_default ? (
                    <span className="rounded-qb-xs bg-qb-fill px-2 py-0.5 text-qb-micro font-normal text-qb-ink-secondary">
                      {t('orders.checkout.default_address')}
                    </span>
                  ) : null}
                </span>
              }
              description={addressLines(saved).map((line, index) => (
                <Fragment key={line}>
                  {index > 0 ? ' · ' : null}
                  <bdi>{line}</bdi>
                </Fragment>
              ))}
            />
          ))}
          <RadioCard
            name="address"
            value="new"
            checked={showForm}
            onChange={() => onChoiceChange({ kind: 'new' })}
            icon={<Plus />}
            label={t('orders.checkout.new_address')}
          />
        </div>
      ) : null}

      {showForm ? (
        <div className="[display:grid] grid-cols-1 gap-4 qb-tablet:grid-cols-2">
          {FIELDS.map(({ field, autoComplete, wide, required, type }) => (
            <Field
              key={field}
              label={t(`orders.checkout.fields.${field}`)}
              error={errors[field]}
              required={required}
              className={cn(wide && 'qb-tablet:col-span-2')}
            >
              {(control) => (
                <Input
                  {...control}
                  type={type ?? 'text'}
                  autoComplete={autoComplete}
                  placeholder={t(`orders.checkout.placeholders.${field}`)}
                  value={address[field] ?? ''}
                  onChange={(event) => onAddressChange({ ...address, [field]: event.target.value })}
                  className="rounded-qb-md"
                />
              )}
            </Field>
          ))}
        </div>
      ) : null}
    </div>
  );
}
