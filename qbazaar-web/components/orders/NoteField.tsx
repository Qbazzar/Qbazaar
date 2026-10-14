'use client';

import { useId, type ReactNode } from 'react';

import { Textarea } from '@/components/design-system/Input';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';
import { cn } from '@/lib/utils';

import { dealLabelClass } from './DealFormParts';

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
  /** Classes of the text box itself, e.g. the 16 px gap under it on the deal forms. */
  textareaClassName?: string;
}

/** The counter appears once the text gets close to the limit, so the field stays quiet until it matters. */
const COUNTER_FROM = 40;

/**
 * Free-text field for messages, reasons and notes, as the message box of offer.html: the 15 px
 * label ("Message (optional)"), an r10 box with 14 px padding and 15 px text, and the characters
 * left near the limit.
 */
export function NoteField({ label, value, onChange, max, error, placeholder, hint, required, rows = 3, className, textareaClassName }: NoteFieldProps) {
  const id = useId();
  const remaining = Math.max(0, max - value.length);
  const left = remaining <= COUNTER_FROM ? tPlural('orders.common.chars_left', remaining) : null;
  const hintId = hint || left ? `${id}-hint` : undefined;
  const errorId = error ? `${id}-error` : undefined;

  return (
    <div className={cn('font-qb', className)}>
      <label htmlFor={id} className={dealLabelClass}>
        {label}
        {required ? (
          <span aria-hidden="true" className="text-qb-brand">
            {' *'}
          </span>
        ) : (
          ` ${t('orders.common.optional')}`
        )}
      </label>
      <Textarea
        id={id}
        rows={rows}
        maxLength={max}
        placeholder={placeholder}
        value={value}
        required={required}
        aria-invalid={error ? true : undefined}
        aria-describedby={[hintId, errorId].filter(Boolean).join(' ') || undefined}
        onChange={(event) => onChange(event.target.value)}
        className={cn('block min-h-0 p-3.5 text-qb-body-sm text-qb-black', textareaClassName)}
      />
      {hintId ? (
        <p id={hintId} className="mt-2 text-qb-caption text-qb-ink-subtle">
          {hint}
          {hint && left ? <span className="mt-1 block">{left}</span> : left}
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

/** Error that is not tied to one field, read out as soon as it appears. */
export function FormError({ children }: { children: ReactNode }) {
  if (!children) return null;
  return (
    <p role="alert" className="text-qb-caption text-qb-danger">
      {children}
    </p>
  );
}
