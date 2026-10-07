'use client';

import { t } from '@/lib/i18n/messages';

import { parsePrice } from './filter-values';
import { RangeFields } from './RangeFields';

interface PriceRangeFieldsProps {
  min: number | null;
  max: number | null;
  onChange: (next: { priceMin: number | null; priceMax: number | null }) => void;
}

/** Min – max price inputs in whole riyals. */
export function PriceRangeFields({ min, max, onChange }: PriceRangeFieldsProps) {
  return (
    <RangeFields
      min={min}
      max={max}
      parse={parsePrice}
      step={1}
      onChange={(priceMin, priceMax) => onChange({ priceMin, priceMax })}
      minLabel={t('catalog.filters.price_min_label', 'أقل سعر')}
      maxLabel={t('catalog.filters.price_max_label', 'أعلى سعر')}
      errorMessage={t('catalog.filters.price_range_error', 'يجب ألا يقل أعلى سعر عن أقل سعر')}
    />
  );
}
