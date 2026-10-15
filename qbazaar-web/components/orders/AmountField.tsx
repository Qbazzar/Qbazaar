'use client';

import { useId, type ReactNode } from 'react';

import { currencyLabel } from '@/lib/orders/money';
import { cn } from '@/lib/utils';

import { dealLabelClass } from './DealFormParts';

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
 * Money input of the offer, settlement and withdrawal forms, as "Your Offer" on offer.html: an r10
 * box with 14 px padding holding the grey currency and a bare 15 px input, with a decimal keyboard.
 * The value stays the typed text; `validateAmount` turns it into an exact decimal string.
 */
export function AmountField({ label, value, onChange, error, hint, currency = 'QAR', required, autoFocus, className }: AmountFieldProps) {
  const id = useId();
  const hintId = hint ? `${id}-hint` : undefined;
  const errorId = error ? `${id}-error` : undefined;
  const currencyText = currencyLabel(currency);

  return (
    <div className={cn('font-qb', className)}>
      <label htmlFor={id} className={dealLabelClass}>
        {label}
        <span className="sr-only"> ({currencyText})</span>
      </label>
      <div
        className={cn(
          'flex items-center rounded-qb-md border bg-qb-surface p-3.5',
          'focus-within:border-qb-brand focus-within:ring-2 focus-within:ring-qb-brand/20',
          error ? 'border-qb-danger' : 'border-qb-line',
        )}
      >
        <span aria-hidden="true" className="me-2 shrink-0 leading-[normal] text-qb-ink-subtle">
          {currencyText}
        </span>
        <input
          id={id}
          type="text"
          inputMode="decimal"
          autoComplete="off"
          placeholder="0"
          autoFocus={autoFocus}
          required={required}
          aria-invalid={error ? true : undefined}
          aria-describedby={[hintId, errorId].filter(Boolean).join(' ') || undefined}
          value={value}
          onChange={(event) => onChange(event.target.value)}
          className="w-full min-w-0 bg-transparent px-0.5 py-px font-qb text-qb-body-sm text-qb-black outline-none placeholder:text-qb-ink-muted"
        />
      </div>
      {hint ? (
        <p id={hintId} className="mt-2 text-qb-caption text-qb-ink-subtle">
          {hint}
        </p>
      ) : null}
      {error ? (
        <p id={errorId} role="alert" className="mt-2 text-qb-caption text-qb-danger">
          {error}
        </p>
      ) : null}
    </div>
  );
}
