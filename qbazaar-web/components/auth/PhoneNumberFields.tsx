'use client';

import { useId, type ReactNode, type Ref } from 'react';

import { Input } from '@/components/design-system/Input';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

import { authInputClass } from './AuthFooter';

/**
 * The country codes of enter-number.html. The API only takes Qatari numbers
 * (`^\+974[0-9]{8}$`), so the others are listed but cannot be picked.
 */
const COUNTRY_CODES = [
  { dial: '+974', nameKey: 'auth.phone.countries.qa' },
  { dial: '+962', nameKey: 'auth.phone.countries.jo' },
  { dial: '+968', nameKey: 'auth.phone.countries.om' },
  { dial: '+49', nameKey: 'auth.phone.countries.de' },
  { dial: '+970', nameKey: 'auth.phone.countries.ps' },
] as const;

const SUPPORTED_DIAL = '+974';
const LOCAL_DIGITS = 8;

/** The 8 local digits of a `+974…` value (or of whatever was typed or pasted). */
export function localDigitsOf(value: string): string {
  const digits = value.replace(/\D/g, '');
  return (digits.startsWith('974') && digits.length > LOCAL_DIGITS ? digits.slice(3) : digits).slice(0, LOCAL_DIGITS);
}

/** "+974 ••• ••45": the number on the code screen, all but its last two digits hidden. */
export function maskPhoneForCode(phone: string): string {
  const local = localDigitsOf(phone);
  return `${SUPPORTED_DIAL} ••• ••${local.slice(-2)}`;
}

/** Full E.164 value for the API, or '' while nothing is typed. */
export function toQatarPhone(localDigits: string): string {
  return localDigits ? `${SUPPORTED_DIAL}${localDigits}` : '';
}

export interface PhoneNumberFieldsProps {
  /** Full E.164 value, e.g. "+97455123456". */
  value: string;
  onChange: (next: string) => void;
  onBlur?: () => void;
  name?: string;
  error?: ReactNode;
  disabled?: boolean;
  inputRef?: Ref<HTMLInputElement>;
  /** Hides the "You will then receive a text message…" note. */
  hideNote?: boolean;
  className?: string;
}

/**
 * "Code Country" select + "Phone number" field of enter-number.html (`.qb-phone`):
 * side by side with a 190 px select, stacked on phones, then the SMS note.
 */
export function PhoneNumberFields({
  value,
  onChange,
  onBlur,
  name,
  error,
  disabled,
  inputRef,
  hideNote,
  className,
}: PhoneNumberFieldsProps) {
  const id = useId();
  const selectId = `${id}-country`;
  const inputId = `${id}-number`;
  const errorId = `${id}-error`;
  const noteId = `${id}-note`;
  const describedBy = [error ? errorId : null, hideNote ? null : noteId].filter(Boolean).join(' ') || undefined;

  return (
    <div className={cn('font-qb', className)}>
      <div className="flex flex-col gap-[18px] qb-tablet:flex-row qb-tablet:gap-3.5">
        <div className="flex flex-col gap-2 qb-tablet:w-[190px] qb-tablet:shrink-0">
          <label htmlFor={selectId} className="text-qb-body text-qb-ink-body">
            {t('auth.phone.country_label')}
          </label>
          <select
            id={selectId}
            defaultValue={SUPPORTED_DIAL}
            disabled={disabled}
            className="h-[52px] w-full rounded-qb-md border border-qb-field-border bg-qb-surface px-4 font-qb text-qb-body text-qb-ink outline-none focus-visible:border-qb-brand disabled:cursor-not-allowed disabled:bg-qb-fill"
          >
            {COUNTRY_CODES.map((country) => (
              <option key={country.dial} value={country.dial} disabled={country.dial !== SUPPORTED_DIAL}>
                {`${t(country.nameKey)} ${country.dial}`}
              </option>
            ))}
          </select>
        </div>
        <div className="flex min-w-0 flex-1 flex-col gap-2">
          <label htmlFor={inputId} className="text-qb-body text-qb-ink-body">
            {t('auth.phone.number_label')}
          </label>
          <Input
            ref={inputRef}
            id={inputId}
            name={name}
            type="tel"
            inputMode="numeric"
            autoComplete="tel-national"
            dir="ltr"
            value={localDigitsOf(value)}
            onChange={(event) => onChange(toQatarPhone(localDigitsOf(event.target.value)))}
            onBlur={onBlur}
            placeholder={t('auth.phone.number_placeholder')}
            aria-invalid={error ? true : undefined}
            aria-describedby={describedBy}
            required
            disabled={disabled}
            className={cn(authInputClass, 'rtl:text-right')}
          />
        </div>
      </div>
      {error ? (
        <p id={errorId} role="alert" className="mt-2 text-qb-caption text-qb-danger">
          {error}
        </p>
      ) : null}
      {hideNote ? null : (
        <p id={noteId} className="mt-3.5 text-qb-micro text-qb-auth-note">
          {t('auth.phone.note')}
        </p>
      )}
    </div>
  );
}
