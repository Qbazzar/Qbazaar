'use client';

import { useId } from 'react';

import { Input } from '@/components/design-system/Input';
import { t } from '@/lib/i18n/messages';

import { isPriceRangeInvalid, parsePrice } from './filter-values';

interface PriceRangeFieldsProps {
  min: number | null;
  max: number | null;
  onChange: (next: { priceMin: number | null; priceMax: number | null }) => void;
}

const field = 'h-11 rounded-qb-lg text-qb-caption';

/** Min – max price inputs in QAR; a maximum below the minimum is flagged and announced. */
export function PriceRangeFields({ min, max, onChange }: PriceRangeFieldsProps) {
  const id = useId();
  const errorId = `${id}-error`;
  const invalid = isPriceRangeInvalid(min, max);

  return (
    <div>
      <div className="flex items-center gap-3">
        <label htmlFor={`${id}-min`} className="sr-only">
          {t('catalog.filters.price_min_label', 'أقل سعر')}
        </label>
        <Input
          id={`${id}-min`}
          type="number"
          inputMode="numeric"
          min={0}
          step={1}
          placeholder={t('common.min', 'الأدنى')}
          value={min ?? ''}
          onChange={(event) => onChange({ priceMin: parsePrice(event.target.value), priceMax: max })}
          className={field}
        />
        <span aria-hidden="true" className="text-qb-caption text-qb-ink-muted">
          -
        </span>
        <label htmlFor={`${id}-max`} className="sr-only">
          {t('catalog.filters.price_max_label', 'أعلى سعر')}
        </label>
        <Input
          id={`${id}-max`}
          type="number"
          inputMode="numeric"
          min={0}
          step={1}
          placeholder={t('common.max', 'الأعلى')}
          value={max ?? ''}
          aria-invalid={invalid || undefined}
          aria-describedby={invalid ? errorId : undefined}
          onChange={(event) => onChange({ priceMin: min, priceMax: parsePrice(event.target.value) })}
          className={field}
        />
      </div>
      <p id={errorId} role="alert" className="mt-2 text-qb-caption text-qb-danger empty:hidden">
        {invalid ? t('catalog.filters.price_range_error', 'يجب ألا يقل أعلى سعر عن أقل سعر') : null}
      </p>
    </div>
  );
}
