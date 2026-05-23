'use client';

import { forwardRef, useCallback } from 'react';
import { cn } from '@/lib/utils';
import { t } from '@/lib/i18n/messages';

/**
 * Qatar phone input.
 *
 * Renders a locked `+974` prefix and an 8-digit input. The value handed to RHF
 * is the full E.164 string (e.g. `+97455123456`) so the backend can validate
 * with the contract's `^\+974[0-9]{8}$` regex.
 *
 * The DOM control accepts only digits and caps at 8; pasted input is sanitised.
 */
export interface PhoneInputProps {
  /** Full E.164 value, e.g. "+97455123456" — controlled by RHF. */
  value: string;
  onChange: (next: string) => void;
  onBlur?: () => void;
  id?: string;
  name?: string;
  placeholder?: string;
  ariaInvalid?: boolean;
  ariaDescribedBy?: string;
  disabled?: boolean;
  className?: string;
}

const COUNTRY_PREFIX = '+974';

function stripPrefix(v: string): string {
  const cleaned = v.replace(/\D/g, '');
  // If the user pasted a full number with a leading "974" treat it as the prefix.
  if (cleaned.startsWith('974')) return cleaned.slice(3, 11);
  return cleaned.slice(0, 8);
}

export const PhoneInput = forwardRef<HTMLInputElement, PhoneInputProps>(
  function PhoneInput(
    {
      value,
      onChange,
      onBlur,
      id,
      name,
      placeholder,
      ariaInvalid,
      ariaDescribedBy,
      disabled,
      className,
    },
    ref,
  ) {
    const localDigits = value.startsWith(COUNTRY_PREFIX)
      ? value.slice(COUNTRY_PREFIX.length)
      : value.replace(/\D/g, '').slice(0, 8);

    const handleChange = useCallback(
      (next: string) => {
        const digits = stripPrefix(next);
        onChange(digits.length === 0 ? '' : `${COUNTRY_PREFIX}${digits}`);
      },
      [onChange],
    );

    return (
      <div
        className={cn(
          'flex items-stretch gap-2',
          // The prefix sits to the start in both LTR and RTL thanks to flex order.
          className,
        )}
        dir="ltr"
      >
        <span
          aria-hidden="true"
          className="inline-flex h-10 select-none items-center gap-1 rounded-lg border border-input bg-muted/40 px-3 text-sm font-medium text-foreground"
        >
          {t('auth.phone.country_prefix', '+974')}
        </span>
        <input
          ref={ref}
          id={id}
          name={name}
          type="tel"
          inputMode="numeric"
          autoComplete="tel-national"
          dir="ltr"
          value={localDigits}
          onChange={(e) => handleChange(e.target.value)}
          onBlur={onBlur}
          placeholder={placeholder ?? '5512 4488'}
          aria-invalid={ariaInvalid}
          aria-describedby={ariaDescribedBy}
          disabled={disabled}
          maxLength={8}
          className={cn(
            'flex h-10 w-full min-w-0 rounded-lg border border-input bg-background px-3 py-2 text-base tracking-wide outline-none transition-colors',
            'placeholder:text-muted-foreground',
            'focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50',
            'disabled:pointer-events-none disabled:opacity-50',
            'aria-invalid:border-destructive aria-invalid:ring-3 aria-invalid:ring-destructive/20',
            'md:text-sm',
          )}
        />
      </div>
    );
  },
);
