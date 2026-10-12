'use client';

import { useId, useState, type FormEvent } from 'react';

import { Button } from '@/components/design-system/Button';
import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { SearchIcon } from '@/components/design-system/icons';
import { Input } from '@/components/design-system/Input';
import { getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import { findCategoryBySlug } from '@/store/categories';
import type { AdFacets, CategoryNode, Location, SearchFacets } from '@/lib/api/types';

import { ActiveFilterChips } from './ActiveFilterChips';
import { activeFilters } from './active-filters';
import { CategoryOptions } from './CategoryOptions';
import { CustomFieldFilters, filterableFields } from './CustomFieldFilters';
import { FilterGroup } from './FilterGroup';
import {
  AD_TYPES,
  CONDITIONS,
  SELLER_TYPES,
  SHIPPING_OPTIONS,
  countActiveFilters,
  filtersEqual,
  hasInvalidRange,
  normalizeFilters,
  type FilterValues,
} from './filter-values';
import { CityOptions, CitySelect } from './LocationFields';
import { CheckOption, OptionList } from './OptionList';
import { PriceRangeFields } from './PriceRangeFields';
import { PriceSlider } from './PriceSlider';

export type FilterGroupKey = 'keyword' | 'category' | 'price' | 'location' | 'condition' | 'adType' | 'shipping' | 'sellerType' | 'buyNow' | 'customFields';

export interface FilterPanelProps {
  /** Groups in display order; each page lists what its endpoint can filter. */
  groups: FilterGroupKey[];
  /** The applied filters; the panel edits a draft until "Apply Filter". */
  values: FilterValues;
  onApply: (next: FilterValues) => void;
  onReset: () => void;
  categories?: CategoryNode[];
  locations?: Location[];
  /** Search's top-level counts (categories, places). */
  facets?: SearchFacets | null;
  /** `meta.facets` of the last response: counts for the other groups, re-read on every response. */
  adFacets?: AdFacets | null;
  /** The page's own category when the panel has no category group; its fields are the ones filtered. */
  categorySlug?: string | null;
  /** The search words are a filter (a category page) and not the page's subject (search). */
  keywordIsFilter?: boolean;
}

interface PanelProps extends FilterPanelProps {
  variant: 'sidebar' | 'sheet';
  /** Applies the filters without a removed chip; defaults to `onApply`. */
  onRemoveFilter?: (next: FilterValues) => void;
  /** "Apply Filter" with the applied filters unchanged, e.g. to close the sheet. */
  onKeep?: () => void;
}

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
export function FilterPanel({ variant, groups, values, onApply, onReset, onRemoveFilter = onApply, onKeep, categories, locations, facets, adFacets, categorySlug, keywordIsFilter = false }: PanelProps) {
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

  const activeCount = countActiveFilters(values, { keyword: keywordIsFilter });
  const invalid = hasInvalidRange(draft);
  const dirty = !filtersEqual(normalizeFilters(draft), values);
  // The card's button is pale while there is nothing to apply; the sheet's bar always submits, and closes it when unchanged.
  const canApply = !invalid && (dirty || activeCount > 0);
  const fieldsCategory = draft.category ?? categorySlug;
  const customFields = filterableFields(fieldsCategory ? findCategoryBySlug(categories, fieldsCategory)?.custom_fields : null);

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (invalid) return;
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
                startIcon={<Icon icon={SearchIcon} />}
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
          <FilterGroup key={key} title={t('catalog.filters.price', 'السعر')} collapsible={false} bodyClassName="mt-2.5">
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
                options={CONDITIONS.map((value) => ({ value, label: t(`ads.condition.${value}`), count: adFacets?.condition[value] }))}
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
                options={AD_TYPES.map((value) => ({ value, label: t(`catalog.filters.ad_type.${value}`), count: adFacets?.ad_type[value] }))}
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
                options={SHIPPING_OPTIONS.map((value) => ({ value, label: t(`catalog.filters.shipping_options.${value}`), count: adFacets?.shipping[value] }))}
                onChange={(shipping) => patch({ shipping: SHIPPING_OPTIONS.find((value) => value === shipping) ?? null })}
              />
            )}
          </FilterGroup>
        );
      case 'sellerType':
        return (
          <FilterGroup key={key} title={t('catalog.filters.seller', 'البائع')}>
            {(headingId) => (
              <OptionList
                name={`${id}-seller-type`}
                labelledBy={headingId}
                value={draft.sellerType}
                options={SELLER_TYPES.map((value) => ({ value, label: t(`catalog.filters.seller_options.${value}`), count: adFacets?.seller_type[value] }))}
                onChange={(sellerType) => patch({ sellerType: SELLER_TYPES.find((value) => value === sellerType) ?? null })}
              />
            )}
          </FilterGroup>
        );
      case 'buyNow':
        return (
          <FilterGroup key={key} title={t('catalog.filters.direct_purchase', 'الشراء المباشر')}>
            {() => (
              <CheckOption
                label={t('catalog.filters.buy_now_option', 'اشترِ الآن')}
                count={adFacets?.buy_now.true}
                checked={draft.buyNow}
                onChange={(buyNow) => patch({ buyNow })}
              />
            )}
          </FilterGroup>
        );
      case 'customFields':
        if (customFields.length === 0) return null;
        // The sheet lists the fields under their own labels (618:26974).
        return sheet ? (
          <div key={key} className="py-2.5">
            <CustomFieldFilters compact fields={customFields} counts={adFacets?.custom_fields} value={draft.customFields} onChange={(next) => patch({ customFields: next })} />
          </div>
        ) : (
          <FilterGroup key={key} title={t('catalog.filters.details', 'التفاصيل')}>
            {() => <CustomFieldFilters fields={customFields} counts={adFacets?.custom_fields} value={draft.customFields} onChange={(next) => patch({ customFields: next })} />}
          </FilterGroup>
        );
    }
  };

  const reset = () => {
    setDraft(values);
    if (activeCount > 0) onReset();
  };

  const chips = (
    <ActiveFilterChips
      chips={activeFilters(values, { keyword: keywordIsFilter, categories, locations, locale })}
      onRemove={onRemoveFilter}
      focusNextAfterRemove={variant === 'sidebar'}
    />
  );

  const resetLabel = t('catalog.filters.reset_all', 'إعادة ضبط الكل');
  // A plain orange word in the reference, with no hover colour.
  const resetButton = (
    <button type="button" onClick={reset} className={cn('cursor-pointer text-qb-caption text-qb-brand', focusRing)}>
      {resetLabel}
    </button>
  );
  const titleText = t('catalog.filters.title', 'الفلتر');
  const titleClass = 'font-qb text-qb-h3 leading-normal font-medium tracking-normal text-qb-ink';

  if (sheet) {
    // category.html on phones: the card's content in the sheet, and the fixed orange "Show Results" bar at
    // the bottom. The sheet's own (screen-reader) title names the dialog, so the visible "Filter" is no heading.
    return (
      <form onSubmit={submit} noValidate aria-label={titleText} className="flex min-h-0 flex-1 flex-col font-qb text-qb-ink">
        <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain p-6 pb-2">
          <div className="mb-[22px] flex items-center justify-between gap-4">
            <span className={titleClass}>{titleText}</span>
            {resetButton}
          </div>
          <div>
            {chips}
            {sheetOrder(groups).map(group)}
          </div>
        </div>
        <button
          type="submit"
          disabled={invalid}
          className={cn(
            'w-full shrink-0 cursor-pointer bg-qb-brand p-4 font-qb text-qb-body-sm leading-normal font-semibold text-qb-on-brand disabled:cursor-default disabled:opacity-50',
            focusRing,
            '-outline-offset-2',
          )}
        >
          {t('catalog.filters.show_results', t('catalog.filters.apply', 'تطبيق الفلتر'))}
        </button>
      </form>
    );
  }

  const titleId = `${id}-title`;
  // The card's button keeps the pale orange of category.html, without the disabled fade, until there is something to apply.
  return (
    <form onSubmit={submit} noValidate aria-labelledby={titleId} className="font-qb">
      <div className="mb-[22px] flex items-center justify-between gap-4">
        <h2 id={titleId} className={titleClass}>
          {titleText}
        </h2>
        {resetButton}
      </div>
      {/* Its own box, so the first group under the title has no hairline above it. */}
      <div>
        {chips}
        {groups.map(group)}
      </div>
      <Button
        type="submit"
        fullWidth
        aria-disabled={!canApply || undefined}
        className={cn(
          'mt-3.5 h-auto rounded-qb-lg p-3.5 font-medium aria-disabled:pointer-events-auto aria-disabled:opacity-100',
          !canApply && 'bg-(--color-qb-brand-pale) hover:bg-(--color-qb-brand-pale) hover:brightness-[.96]',
        )}
      >
        {t('catalog.filters.apply', 'تطبيق الفلتر')}
      </Button>
    </form>
  );
}
