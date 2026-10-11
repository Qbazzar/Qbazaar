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
  countActiveFilters,
  filtersEqual,
  hasInvalidRange,
  normalizeFilters,
  type FilterValues,
} from './filter-values';
import { CityOptions, CitySelect } from './LocationFields';
import { OptionList } from './OptionList';
import { PriceRangeFields } from './PriceRangeFields';
import { PriceSlider } from './PriceSlider';

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
  /** Applies the filters without a removed chip; defaults to `onApply`. */
  onRemoveFilter?: (next: FilterValues) => void;
  /** "Apply Filter" with the applied filters unchanged, e.g. to close the sheet. */
  onKeep?: () => void;
}

/**
 * The sheet is at least as tall as 618:26974 (584 px, 88 px of it the handle
 * and the title row above this form), so the City / Region panel has room to
 * open below its field (264:4818) instead of flipping over the title.
 */
const sheetBodyHeight = 'min-h-[min(496px,calc(90dvh-88px))]';

/** The sheet leads with the fields of 618:26974; the radio groups follow in the page's order. */
const SHEET_FIELDS: FilterGroupKey[] = ['keyword', 'price', 'location'];

function sheetOrder(groups: FilterGroupKey[]): FilterGroupKey[] {
  return [...groups.filter((key) => SHEET_FIELDS.includes(key)), ...groups.filter((key) => !SHEET_FIELDS.includes(key))];
}

/**
 * The filter form of the listing pages: the sidebar card on desktop (250:4405)
 * and the body of the bottom sheet on tablets and phones (618:26974). It stays
 * mounted when new filters apply, so the focus survives, and restarts its
 * draft from them.
 */
export function FilterPanel({ variant, groups, values, onApply, onReset, onRemoveFilter = onApply, onKeep, categories, locations, facets }: PanelProps) {
  const id = useId();
  const locale = getLocale();
  const sheet = variant === 'sheet';
  const [draft, setDraft] = useState(values);
  const appliedKey = JSON.stringify(values);
  const [draftBase, setDraftBase] = useState(appliedKey);
  if (draftBase !== appliedKey) {
    setDraftBase(appliedKey);
    setDraft(values);
  }
  const patch = (next: Partial<FilterValues>) => setDraft((current) => ({ ...current, ...next }));

  const invalid = hasInvalidRange(draft);
  const dirty = !filtersEqual(normalizeFilters(draft), values);
  // The card's button is pale while nothing is chosen (250:4405); the sheet's is always solid and then just closes it (618:26974).
  const canApply = !invalid && (sheet || dirty || countActiveFilters(values) > 0);
  const customFields = filterableFields(draft.category ? findCategoryBySlug(categories, draft.category)?.custom_fields : null);

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!canApply) return;
    if (dirty) onApply(normalizeFilters(draft));
    else onKeep?.();
  };

  const group = (key: FilterGroupKey) => {
    switch (key) {
      case 'keyword':
        return (
          <FilterGroup key={key} field={sheet} title={t('catalog.filters.keyword', 'البحث')} collapsible={false}>
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
          <FilterGroup key={key} title={t('catalog.filters.category', 'القسم')}>
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
        // The card has the range slider (250:4405); the sheet the Min / Max fields (618:26974).
        return sheet ? (
          <FilterGroup key={key} field title={t('catalog.filters.price_range', 'نطاق السعر (ر.ق)')} collapsible={false}>
            {() => <PriceRangeFields min={draft.priceMin} max={draft.priceMax} onChange={(next) => patch(next)} />}
          </FilterGroup>
        ) : (
          <FilterGroup key={key} title={t('catalog.filters.price', 'السعر')} collapsible={false}>
            {() => <PriceSlider min={draft.priceMin} max={draft.priceMax} onChange={(next) => patch(next)} />}
          </FilterGroup>
        );
      case 'location':
        // The card lists the cities as radios (69:467); the sheet has the "City / Region" select (618:26974).
        return sheet ? (
          <FilterGroup key={key} field title={t('catalog.filters.city', 'المدينة / المنطقة')} collapsible={false}>
            {(headingId) => (
              <CitySelect
                labelledBy={headingId}
                value={draft.location}
                locations={locations}
                counts={facets?.locations}
                onChange={(location) => patch({ location })}
              />
            )}
          </FilterGroup>
        ) : (
          <FilterGroup key={key} title={t('catalog.filters.cities', 'المدن')}>
            {(headingId) => (
              <CityOptions
                name={`${id}-location`}
                labelledBy={headingId}
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
          <FilterGroup key={key} title={t('catalog.filters.condition', 'الحالة')}>
            {(headingId) => (
              <OptionList
                name={`${id}-condition`}
                labelledBy={headingId}
                value={draft.condition}
                options={CONDITIONS.map((value) => ({ value, label: t(`ads.condition.${value}`), count: facets?.conditions?.[value] }))}
                onChange={(condition) => patch({ condition: CONDITIONS.find((value) => value === condition) ?? null })}
              />
            )}
          </FilterGroup>
        );
      case 'adType':
        return (
          <FilterGroup key={key} title={t('catalog.filters.offer', 'نوع الإعلان')}>
            {(headingId) => (
              <OptionList
                name={`${id}-ad-type`}
                labelledBy={headingId}
                value={draft.adType}
                options={AD_TYPES.map((value) => ({ value, label: t(`catalog.filters.ad_type.${value}`) }))}
                onChange={(adType) => patch({ adType: AD_TYPES.find((value) => value === adType) ?? null })}
              />
            )}
          </FilterGroup>
        );
      case 'shipping':
        return (
          <FilterGroup key={key} title={t('catalog.filters.shipping', 'الشحن والتوصيل')}>
            {(headingId) => (
              <OptionList
                name={`${id}-shipping`}
                labelledBy={headingId}
                value={draft.shipping}
                options={SHIPPING_OPTIONS.map((value) => ({ value, label: t(`catalog.filters.shipping_options.${value}`) }))}
                onChange={(shipping) => patch({ shipping: SHIPPING_OPTIONS.find((value) => value === shipping) ?? null })}
              />
            )}
          </FilterGroup>
        );
      case 'customFields':
        if (customFields.length === 0) return null;
        // The sheet lists the fields under their own labels (618:26974).
        return sheet ? (
          <div key={key} className="py-2.5">
            <CustomFieldFilters compact fields={customFields} value={draft.customFields} onChange={(next) => patch({ customFields: next })} />
          </div>
        ) : (
          <FilterGroup key={key} title={t('catalog.filters.details', 'التفاصيل')}>
            {() => <CustomFieldFilters fields={customFields} value={draft.customFields} onChange={(next) => patch({ customFields: next })} />}
          </FilterGroup>
        );
    }
  };

  const reset = () => {
    setDraft(values);
    if (countActiveFilters(values) > 0) onReset();
  };

  const chips = (
    <ActiveFilterChips
      chips={activeFilters(values, { categories, locations, locale })}
      onRemove={onRemoveFilter}
      focusNextAfterRemove={variant === 'sidebar'}
    />
  );

  // 16 / 500 on the card (250:4405), 16 / 700 in the sheet (618:26974).
  const apply = (
    <Button type="submit" fullWidth disabled={!canApply} className={cn('h-[46px] rounded-qb-lg', sheet ? 'font-bold' : 'font-medium')}>
      {t('catalog.filters.apply', 'تطبيق الفلتر')}
    </Button>
  );
  const resetLabel = t('catalog.filters.reset_all', 'إعادة ضبط الكل');

  if (sheet) {
    return (
      <form onSubmit={submit} noValidate aria-label={t('catalog.filters.title', 'الفلتر')} className={cn('flex flex-1 flex-col font-qb text-qb-ink', sheetBodyHeight)}>
        {/* Only the fields scroll; the buttons stay at the bottom of the sheet. */}
        <div className="-mx-6 min-h-0 flex-1 overflow-y-auto overscroll-contain px-6 pb-2">
          {chips}
          {sheetOrder(groups).map(group)}
        </div>
        <div className="-mx-6 flex flex-col items-center gap-3 border-t border-qb-line bg-qb-surface px-6 pt-4 pb-5">
          {apply}
          <button type="button" onClick={reset} className={cn('rounded-qb-xs text-qb-body font-medium text-qb-brand hover:text-qb-brand-active', focusRing)}>
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
