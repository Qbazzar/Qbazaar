'use client';

import type { ReactNode } from 'react';

import { Field } from '@/components/design-system/Field';
import { Input } from '@/components/design-system/Input';
import { currencyLabel } from '@/lib/orders/money';

export interface AmountFieldProps {
  label: ReactNode;
  value: string;
  onChange: (value: string) => void;
  error?: string | null;
  hint?: ReactNode;
  currency?: string;
  required?: boolean;
  autoFocus?: boolean;
  className?: string;
}

/**
 * Money input of the offer, settlement and withdrawal forms: a text field
 * with a decimal keyboard and the currency at its end (709:32645). The
 * value stays the typed text; `validateAmount` turns it into an exact
 * decimal string.
 */
export function AmountField({
  label,
  value,
  onChange,
  error,
  hint,
  currency = 'QAR',
  required,
  autoFocus,
  className,
}: AmountFieldProps) {
  return (
    <Field
      label={
        <>
          {label}
          <span className="sr-only"> ({currencyLabel(currency)})</span>
        </>
      }
      hint={hint}
      error={error ?? undefined}
      required={required}
      className={className}
    >
      {(control) => (
        <div className="relative">
          <Input
            {...control}
            inputMode="decimal"
            autoComplete="off"
            placeholder="0.00"
            autoFocus={autoFocus}
            value={value}
            onChange={(event) => onChange(event.target.value)}
            className="h-12 rounded-qb-lg pe-16"
          />
          <span
            aria-hidden="true"
            className="pointer-events-none absolute inset-y-0 end-4 flex items-center text-qb-caption text-qb-ink-subtle"
          >
            {currencyLabel(currency)}
          </span>
        </div>
      )}
    </Field>
  );
}
