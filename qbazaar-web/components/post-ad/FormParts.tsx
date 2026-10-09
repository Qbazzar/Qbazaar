import type { ChangeEvent, InputHTMLAttributes, ReactNode } from 'react';
import { CircleAlert } from 'lucide-react';

import { cardVariants } from '@/components/design-system/Card';
import { Icon } from '@/components/design-system/Icon';
import { translateMaybeKey } from '@/lib/i18n/messages';
import type { AdFormField } from '@/lib/post-ad/form';
import { cn } from '@/lib/utils';

/** Text boxes of add-ads.html: 15 px text and 14 px side padding on the design-system controls. */
export const controlSize = 'h-[53px] px-3.5 text-qb-body-sm';
/** The same for a select, whose end padding stays clear of its chevron; a value reads in #4B4B4B. */
export const selectSize = 'h-[53px] ps-3.5 text-qb-body-sm text-qb-ink-body';

/** DOM id of a form field, also used to focus the first invalid one. */
export function fieldId(name: AdFormField): string {
  return `post-ad-${name.replace('.', '-')}`;
}

export function errorId(name: AdFormField): string {
  return `${fieldId(name)}-error`;
}

/** aria wiring for a control: its error (when any) and optional hint ids. */
export function describedBy(name: AdFormField, error: string | undefined, ...extra: Array<string | false | undefined>) {
  const ids = [...extra, error ? errorId(name) : undefined].filter(Boolean).join(' ');
  return {
    'aria-invalid': error ? (true as const) : undefined,
    'aria-describedby': ids || undefined,
  };
}

/** White form card with its 24 px heading (20 px on phones), as on add-ads.html. */
export function FormSection({ title, id, children }: { title: string; id: string; children: ReactNode }) {
  return (
    <section aria-labelledby={id} className={cn(cardVariants({ padding: 'none' }), 'p-5 text-qb-ink qb-tablet:p-7')}>
      <h2 id={id} className="mb-[22px] text-qb-h5 font-semibold tracking-normal qb-tablet:text-qb-h3">
        {title}
      </h2>
      {children}
    </section>
  );
}

/** The 15 px label of add-ads.html; the design-system Field uses the 16 px one of the account forms. */
export function FieldLabel({
  htmlFor,
  id,
  children,
  optional,
}: {
  htmlFor?: string;
  id?: string;
  children: ReactNode;
  optional?: string;
}) {
  return (
    <label htmlFor={htmlFor} id={id} className="mb-2.5 block text-qb-body-sm text-qb-ink-body">
      {children}
      {optional ? <span className="text-qb-ink-subtle"> {optional}</span> : null}
    </label>
  );
}

export function FieldError({ name, message }: { name: AdFormField; message: string | undefined }) {
  if (!message) return null;
  return (
    <p id={errorId(name)} className="mt-2 flex items-start gap-1.5 text-qb-label text-qb-danger">
      <Icon icon={CircleAlert} size="sm" className="mt-px" />
      <span>{translateMaybeKey(message)}</span>
    </p>
  );
}

export interface ChoiceOption<T extends string> {
  value: T;
  label: string;
}

/** Radio cards ("Offering / Looking for", "Pickup Only / Delivery Available"). */
export function ChoiceGroup<T extends string>({
  legend,
  name,
  value,
  options,
  onChange,
  size = 'md',
}: {
  legend: string;
  name: string;
  value: T;
  options: readonly ChoiceOption<T>[];
  onChange: (value: T) => void;
  size?: 'md' | 'lg';
}) {
  return (
    <fieldset className="min-w-0">
      <legend className="mb-2.5 text-qb-body-sm text-qb-ink-body">{legend}</legend>
      <div className="[display:grid] grid-cols-1 gap-3.5 qb-tablet:grid-cols-2">
        {options.map((option) => {
          const checked = option.value === value;
          return (
            <label
              key={option.value}
              className={cn(
                'flex cursor-pointer items-center gap-3 border-[1.5px] text-qb-body-sm font-medium transition-colors',
                'has-[input:focus-visible]:outline-2 has-[input:focus-visible]:outline-offset-2 has-[input:focus-visible]:outline-qb-brand-active',
                size === 'lg' ? 'rounded-qb-lg px-[18px] py-[17px]' : 'rounded-qb-md px-4 py-[15px]',
                checked
                  ? 'border-qb-brand bg-qb-brand-soft text-qb-brand-active'
                  : 'border-qb-line bg-qb-surface text-qb-ink hover:bg-qb-hover',
              )}
            >
              <input
                type="radio"
                name={name}
                value={option.value}
                checked={checked}
                onChange={() => onChange(option.value)}
                className="sr-only"
              />
              <span
                aria-hidden="true"
                className={cn(
                  'flex size-[22px] shrink-0 items-center justify-center rounded-full border-2',
                  checked ? 'border-qb-brand' : 'border-qb-ink-disabled',
                )}
              >
                {checked ? <span className="size-[11px] rounded-full bg-qb-brand" /> : null}
              </span>
              {option.label}
            </label>
          );
        })}
      </div>
    </fieldset>
  );
}

/** Amount box with the currency after the number ("0 ... QAR"); the currency is read with the field. */
export function AmountInput({
  id,
  currency,
  invalid,
  className,
  onValueChange,
  'aria-describedby': describedByIds,
  ...props
}: Omit<InputHTMLAttributes<HTMLInputElement>, 'onChange'> & {
  id: string;
  currency: string;
  invalid?: boolean;
  onValueChange: (value: string, event: ChangeEvent<HTMLInputElement>) => void;
}) {
  const currencyId = `${id}-currency`;
  return (
    <div
      className={cn(
        'flex h-[55px] items-center gap-2 rounded-qb-md border bg-qb-surface px-3.5 transition-colors',
        'focus-within:border-qb-brand focus-within:ring-2 focus-within:ring-qb-brand/20',
        invalid ? 'border-qb-danger' : 'border-qb-line',
        props.disabled && 'bg-qb-fill',
        className,
      )}
    >
      <input
        id={id}
        type="text"
        inputMode="decimal"
        autoComplete="off"
        className="h-full min-w-0 flex-1 bg-transparent font-qb text-qb-body-sm text-qb-ink outline-none placeholder:text-qb-placeholder disabled:cursor-not-allowed"
        onChange={(event) => onValueChange(event.target.value, event)}
        aria-describedby={[currencyId, describedByIds].filter(Boolean).join(' ')}
        {...props}
      />
      <span id={currencyId} className="shrink-0 text-qb-body-sm text-qb-ink-subtle">
        {currency}
      </span>
    </div>
  );
}
