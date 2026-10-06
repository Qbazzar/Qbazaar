import { useId, type ReactNode } from 'react';

import { cn } from '@/lib/utils';

/** Props a `Field` hands to its control so label, hint and error are wired up. */
export interface FieldControlProps {
  id: string;
  'aria-describedby'?: string;
  'aria-invalid'?: true;
  required?: boolean;
}

export interface FieldProps {
  label: ReactNode;
  /** Renders the control; spread the props onto the Input/Select/Textarea. */
  children: (control: FieldControlProps) => ReactNode;
  hint?: ReactNode;
  error?: ReactNode;
  required?: boolean;
  /** Visually hide the label but keep it for screen readers. */
  hideLabel?: boolean;
  id?: string;
  className?: string;
}

/**
 * Label + control + hint/error, as on the auth and settings forms
 * (`.qb-field` in Qbazaar-front). The required star follows the reference:
 * brand-coloured, not part of the accessible name.
 */
export function Field({ label, children, hint, error, required, hideLabel, id, className }: FieldProps) {
  const generatedId = useId();
  const controlId = id ?? generatedId;
  const hintId = hint ? `${controlId}-hint` : undefined;
  const errorId = error ? `${controlId}-error` : undefined;
  const describedBy = [hintId, errorId].filter(Boolean).join(' ') || undefined;

  return (
    <div className={cn('flex flex-col gap-2 font-qb', className)}>
      <label
        htmlFor={controlId}
        className={cn('text-qb-body text-qb-ink-body', hideLabel && 'sr-only')}
      >
        {label}
        {required ? (
          <span aria-hidden="true" className="text-qb-brand">
            {' *'}
          </span>
        ) : null}
      </label>
      {children({
        id: controlId,
        'aria-describedby': describedBy,
        'aria-invalid': error ? true : undefined,
        required,
      })}
      {hint ? (
        <p id={hintId} className="text-qb-caption text-qb-ink-subtle">
          {hint}
        </p>
      ) : null}
      {error ? (
        <p id={errorId} className="text-qb-caption text-qb-danger">
          {error}
        </p>
      ) : null}
    </div>
  );
}
