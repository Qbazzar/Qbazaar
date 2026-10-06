'use client';

import { useId, useState, type FormEvent } from 'react';
import { Search } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { Input } from '@/components/design-system/Input';
import { getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import { findCategoryBySlug } from '@/store/categories';
import type { CategoryNode, Location, SearchFacets } from '@/lib/api/types';

import { ActiveFilterChips } from './ActiveFilterChips';
import { activeFilters } from './active-filters';
import { CategoryOptions } from './CategoryOptions';
import { CustomFieldFilters, filterableFields } from './CustomFieldFilters';
import { FilterGroup } from './FilterGroup';
import {
  AD_TYPES,
  CONDITIONS,
  SHIPPING_OPTIONS,
  filtersEqual,
  isPriceRangeInvalid,
  normalizeFilters,
  type FilterValues,
} from './filter-values';
import { LocationFields } from './LocationFields';
import { OptionList } from './OptionList';
import { PriceRangeFields } from './PriceRangeFields';

export type FilterGroupKey = 'keyword' | 'category' | 'price' | 'location' | 'condition' | 'adType' | 'shipping' | 'customFields';

export interface FilterPanelProps {
  /** Groups in display order; each page lists what its endpoint can filter. */
  groups: FilterGroupKey[];
  /** The applied filters; the panel edits a draft until "Apply Filter". */
  values: FilterValues;
  onApply: (next: FilterValues) => void;
  onReset: () => void;
  categories?: CategoryNode[];
  locations?: Location[];
  facets?: SearchFacets | null;
}

interface PanelProps extends FilterPanelProps {
  variant: 'sidebar' | 'sheet';
}

/**
 * The filter form of the listing pages: the sidebar card on desktop (250:4405)
 * and the body of the bottom sheet on tablets and phones (618:26974).
 * Remount it with a `key` of the applied values to reset the draft.
 */
export function FilterPanel({ variant, groups, values, onApply, onReset, categories, locations, facets }: PanelProps) {
  const id = useId();
  const locale = getLocale();
  const compact = variant === 'sheet';
  const [draft, setDraft] = useState(values);
  const patch = (next: Partial<FilterValues>) => setDraft((current) => ({ ...current, ...next }));

  const invalid = isPriceRangeInvalid(draft.priceMin, draft.priceMax);
  const dirty = !filtersEqual(normalizeFilters(draft), values);
  const customFields = filterableFields(draft.category ? findCategoryBySlug(categories, draft.category)?.custom_fields : null);

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!invalid) onApply(normalizeFilters(draft));
  };

  const group = (key: FilterGroupKey) => {
    switch (key) {
      case 'keyword':
        return (
          <FilterGroup key={key} compact={compact} title={t('catalog.filters.keyword', 'البحث')} collapsible={false}>
            {(headingId) => (
              <Input
                type="search"
                aria-labelledby={headingId}
                startIcon={<Icon icon={Search} />}
                placeholder={t('catalog.filters.keyword_placeholder', 'ابحث…')}
                value={draft.keyword}
                onChange={(event) => patch({ keyword: event.target.value })}
                className="h-11 rounded-qb-lg text-qb-caption [&::-webkit-search-cancel-button]:appearance-none"
              />
            )}
          </FilterGroup>
        );
      case 'category':
        return (
          <FilterGroup key={key} compact={compact} title={t('catalog.filters.category', 'القسم')}>
            {(headingId) => (
              <CategoryOptions
                name={`${id}-category`}
                labelledBy={headingId}
                categories={categories}
                value={draft.category}
                counts={facets?.categories}
                onChange={(category) => patch({ category, customFields: {} })}
              />
            )}
          </FilterGroup>
        );
      case 'price':
        return (
          <FilterGroup key={key} compact={compact} title={t('catalog.filters.price', 'السعر')} collapsible={false}>
            {() => (
              <PriceRangeFields min={draft.priceMin} max={draft.priceMax} onChange={(next) => patch(next)} />
            )}
          </FilterGroup>
        );
      case 'location':
        return (
          <FilterGroup
            key={key}
            compact={compact}
            title={compact ? t('catalog.filters.city', 'المدينة / المنطقة') : t('catalog.filters.location', 'الموقع')}
          >
            {() => (
              <LocationFields
                hideCityLabel={compact}
                value={draft.location}
                locations={locations}
                counts={facets?.locations}
                onChange={(location) => patch({ location })}
              />
            )}
          </FilterGroup>
        );
      case 'condition':
        return (
          <FilterGroup key={key} compact={compact} title={t('catalog.filters.condition', 'الحالة')}>
            {(headingId) => (
              <OptionList
                name={`${id}-condition`}
                labelledBy={headingId}
                value={draft.condition}
                anyLabel={t('catalog.filters.any', 'الكل')}
                options={CONDITIONS.map((value) => ({ value, label: t(`ads.condition.${value}`), count: facets?.conditions?.[value] }))}
                onChange={(condition) => patch({ condition: CONDITIONS.find((value) => value === condition) ?? null })}
              />
            )}
          </FilterGroup>
        );
      case 'adType':
        return (
          <FilterGroup key={key} compact={compact} title={t('catalog.filters.offer', 'نوع الإعلان')}>
            {(headingId) => (
              <OptionList
                name={`${id}-ad-type`}
                labelledBy={headingId}
                value={draft.adType}
                anyLabel={t('catalog.filters.any', 'الكل')}
                options={AD_TYPES.map((value) => ({ value, label: t(`catalog.filters.ad_type.${value}`) }))}
                onChange={(adType) => patch({ adType: AD_TYPES.find((value) => value === adType) ?? null })}
              />
            )}
          </FilterGroup>
        );
      case 'shipping':
        return (
          <FilterGroup key={key} compact={compact} title={t('catalog.filters.shipping', 'الشحن والتوصيل')}>
            {(headingId) => (
              <OptionList
                name={`${id}-shipping`}
                labelledBy={headingId}
                value={draft.shipping}
                anyLabel={t('catalog.filters.any', 'الكل')}
                options={SHIPPING_OPTIONS.map((value) => ({ value, label: t(`catalog.filters.shipping_options.${value}`) }))}
                onChange={(shipping) => patch({ shipping: SHIPPING_OPTIONS.find((value) => value === shipping) ?? null })}
              />
            )}
          </FilterGroup>
        );
      case 'customFields':
        return customFields.length > 0 ? (
          <FilterGroup key={key} compact={compact} title={t('catalog.filters.details', 'التفاصيل')}>
            {() => (
              <CustomFieldFilters
                fields={customFields}
                value={draft.customFields}
                onChange={(next) => patch({ customFields: next })}
              />
            )}
          </FilterGroup>
        ) : null;
    }
  };

  const reset = () => {
    setDraft(values);
    onReset();
  };

  const chips = (
    <ActiveFilterChips chips={activeFilters(values, { categories, locations, locale })} onRemove={onApply} />
  );

  const apply = (
    <Button type="submit" fullWidth disabled={!dirty || invalid} className="h-[46px] rounded-qb-lg font-medium">
      {t('catalog.filters.apply', 'تطبيق الفلتر')}
    </Button>
  );
  const resetLabel = t('catalog.filters.reset_all', 'إعادة ضبط الكل');

  if (variant === 'sheet') {
    return (
      <form onSubmit={submit} noValidate aria-label={t('catalog.filters.title', 'الفلتر')} className="font-qb">
        {chips}
        <div>{groups.map(group)}</div>
        <div className="sticky -bottom-6 -mx-6 -mb-6 flex flex-col items-center gap-3 border-t border-qb-line bg-qb-surface px-6 pt-4 pb-5">
          {apply}
          <button type="button" onClick={reset} className={cn('rounded-qb-xs text-qb-body font-medium text-qb-brand', focusRing)}>
            {resetLabel}
          </button>
        </div>
      </form>
    );
  }

  const titleId = `${id}-title`;
  return (
    <form onSubmit={submit} noValidate aria-labelledby={titleId} className="font-qb">
      <div className="flex items-center justify-between gap-4 border-b border-qb-line px-[25px] pt-[25px] pb-[22px]">
        <h2 id={titleId} className="font-qb text-qb-h3 leading-none font-medium tracking-normal text-qb-ink">
          {t('catalog.filters.title', 'الفلتر')}
        </h2>
        <button type="button" onClick={reset} className={cn('rounded-qb-xs text-qb-caption text-qb-brand hover:text-qb-brand-active', focusRing)}>
          {resetLabel}
        </button>
      </div>
      <div className="px-[25px] pt-[22px]">
        {chips}
        {groups.map(group)}
      </div>
      <div className="px-[25px] pt-2 pb-[25px]">{apply}</div>
    </form>
  );
}
