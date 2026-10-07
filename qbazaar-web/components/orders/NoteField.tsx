'use client';

import type { ReactNode } from 'react';

import { Field } from '@/components/design-system/Field';
import { Textarea } from '@/components/design-system/Input';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';

export interface NoteFieldProps {
  label: ReactNode;
  value: string;
  onChange: (value: string) => void;
  max: number;
  error?: string | null;
  placeholder?: string;
  /** Shown above the counter, e.g. what the other side will see. */
  hint?: ReactNode;
  required?: boolean;
  rows?: number;
  className?: string;
}

/** The counter appears once the text gets close to the limit, so the field stays quiet until it matters. */
const COUNTER_FROM = 40;

/** Free-text field for messages, reasons and notes, with the characters left near the limit. */
export function NoteField({ label, value, onChange, max, error, placeholder, hint, required, rows, className }: NoteFieldProps) {
  const remaining = Math.max(0, max - value.length);
  const left = remaining <= COUNTER_FROM ? tPlural('orders.common.chars_left', remaining) : null;
  return (
    <Field
      label={
        required ? (
          label
        ) : (
          <>
            {label} <span className="text-qb-ink-subtle">{t('orders.common.optional')}</span>
          </>
        )
      }
      required={required}
      hint={
        hint && left ? (
          <>
            {hint}
            <span className="mt-1 block">{left}</span>
          </>
        ) : (
          (hint ?? left)
        )
      }
      error={error ?? undefined}
      className={className}
    >
      {(control) => (
        <Textarea
          {...control}
          rows={rows}
          maxLength={max}
          placeholder={placeholder}
          value={value}
          onChange={(event) => onChange(event.target.value)}
          className="rounded-qb-lg"
        />
      )}
    </Field>
  );
}

/** Error that is not tied to one field, read out as soon as it appears. */
export function FormError({ children }: { children: ReactNode }) {
  if (!children) return null;
  return (
    <p role="alert" className="text-qb-caption text-qb-danger">
      {children}
    </p>
  );
}
