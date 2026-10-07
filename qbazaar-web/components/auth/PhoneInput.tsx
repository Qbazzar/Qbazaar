'use client';

import { forwardRef, useCallback } from 'react';

import { Input } from '@/components/design-system/Input';
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
  required?: boolean;
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
      required,
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
      // Phone numbers read left to right in both languages.
      <div className={cn('flex items-stretch gap-2', className)} dir="ltr">
        <span
          aria-hidden="true"
          className="inline-flex h-[52px] shrink-0 select-none items-center rounded-qb-md border border-qb-line bg-qb-fill px-4 font-qb text-qb-body text-qb-ink-body"
        >
          {t('auth.phone.country_prefix', '+974')}
        </span>
        <Input
          // Input passes every prop to <input>; its props type just doesn't declare ref yet.
          {...{ ref }}
          id={id}
          name={name}
          type="tel"
          inputMode="numeric"
          autoComplete="tel-national"
          value={localDigits}
          onChange={(e) => handleChange(e.target.value)}
          onBlur={onBlur}
          placeholder={placeholder ?? '5512 4488'}
          aria-invalid={ariaInvalid || undefined}
          aria-describedby={ariaDescribedBy}
          required={required}
          disabled={disabled}
          maxLength={8}
          className="min-w-0 tracking-wide"
        />
      </div>
    );
  },
);
