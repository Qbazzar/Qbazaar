'use client';

import { useId } from 'react';

import { Input } from '@/components/design-system/Input';
import { t } from '@/lib/i18n/messages';

import { isRangeInvalid } from './filter-values';

interface RangeFieldsProps {
  min: number | null;
  max: number | null;
  /** A typed value as a bound, or null when it is empty or not allowed. */
  parse: (raw: string) => number | null;
  onChange: (min: number | null, max: number | null) => void;
  minLabel: string;
  maxLabel: string;
  /** Shown and announced while the maximum is below the minimum. */
  errorMessage: string;
  step?: number;
}

/** Plain fields without the number spinners (264:4818, 618:26974). */
const field =
  'h-11 rounded-qb-lg text-qb-caption [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none';

/** Min – max number inputs of a filter; a reversed range is flagged and announced. */
export function RangeFields({ min, max, parse, onChange, minLabel, maxLabel, errorMessage, step }: RangeFieldsProps) {
  const id = useId();
  const errorId = `${id}-error`;
  const invalid = isRangeInvalid(min, max);

  return (
    <div>
      <div className="flex items-center gap-3">
        <label htmlFor={`${id}-min`} className="sr-only">
          {minLabel}
        </label>
        <Input
          id={`${id}-min`}
          type="number"
          inputMode="numeric"
          min={0}
          step={step}
          placeholder={t('common.min', 'الأدنى')}
          value={min ?? ''}
          onChange={(event) => onChange(parse(event.target.value), max)}
          className={field}
        />
        <span aria-hidden="true" className="text-qb-caption text-qb-ink-muted">
          -
        </span>
        <label htmlFor={`${id}-max`} className="sr-only">
          {maxLabel}
        </label>
        <Input
          id={`${id}-max`}
          type="number"
          inputMode="numeric"
          min={0}
          step={step}
          placeholder={t('common.max', 'الأعلى')}
          value={max ?? ''}
          aria-invalid={invalid || undefined}
          aria-describedby={invalid ? errorId : undefined}
          onChange={(event) => onChange(min, parse(event.target.value))}
          className={field}
        />
      </div>
      <p id={errorId} role="alert" className="mt-2 text-qb-caption text-qb-danger empty:hidden">
        {invalid ? errorMessage : null}
      </p>
    </div>
  );
}
