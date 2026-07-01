'use client';

/**
 * Filter sidebar for the search page.
 *
 * Composed of four sections — categories, locations, price, condition — plus
 * a "clear all" affordance at the bottom. Every change is bubbled up via
 * `onChange` so the parent can persist it into the URL via `nuqs`. The
 * sidebar is intentionally stateless: state-of-truth lives in the URL.
 */
import { useMemo } from 'react';
import { XIcon } from 'lucide-react';
import { CategoryFilters } from './filters/CategoryFilters';
import { LocationFilters } from './filters/LocationFilters';
import { PriceRangeFilter } from './filters/PriceRangeFilter';
import { ConditionFilter } from './filters/ConditionFilter';
import { CustomFieldFilters } from './filters/CustomFieldFilters';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type {
  AdCondition,
  CategoryField,
  CustomFieldsFilter,
  SearchFacets,
} from '@/lib/api/types';

export interface FilterValues {
  category_slug: string | null;
  location_slug: string | null;
  price_min: number | null;
  price_max: number | null;
  condition: AdCondition | null;
}

interface Props {
  values: FilterValues;
  onChange: (patch: Partial<FilterValues>) => void;
  facets: SearchFacets | null;
  onClearAll: () => void;
  className?: string;
  /** The selected category's custom-field schema (null when none selected). */
  customFieldSchema?: CategoryField[] | null;
  customFields?: CustomFieldsFilter;
  onCustomFieldsChange?: (next: CustomFieldsFilter) => void;
}

export function FilterSidebar({
  values,
  onChange,
  facets,
  onClearAll,
  className,
  customFieldSchema,
  customFields,
  onCustomFieldsChange,
}: Props) {
  const hasAnyFilter = useMemo(
    () =>
      Boolean(
        values.category_slug ||
          values.location_slug ||
          values.price_min !== null ||
          values.price_max !== null ||
          values.condition,
      ),
    [values],
  );

  return (
    <div className={cn('space-y-4', className)}>
      <FilterCard title={t('search.facets.categories', 'الأقسام')}>
        <CategoryFilters
          activeSlug={values.category_slug}
          onChange={(slug) => onChange({ category_slug: slug })}
          counts={facets?.categories ?? null}
        />
      </FilterCard>

      <FilterCard title={t('search.facets.locations', 'المواقع')}>
        <LocationFilters
          activeSlug={values.location_slug}
          onChange={(slug) => onChange({ location_slug: slug })}
        />
      </FilterCard>

      <FilterCard title={t('search.facets.price', 'السعر')}>
        <PriceRangeFilter
          min={values.price_min}
          max={values.price_max}
          onChange={(next) => onChange(next)}
          buckets={facets?.price_buckets ?? null}
        />
      </FilterCard>

      <FilterCard title={t('search.facets.condition', 'الحالة')}>
        <ConditionFilter
          value={values.condition}
          onChange={(next) => onChange({ condition: next })}
          counts={facets?.conditions ?? null}
        />
      </FilterCard>

      {customFieldSchema && customFieldSchema.length > 0 && onCustomFieldsChange ? (
        <FilterCard title={t('search.facets.details', 'تفاصيل')}>
          <CustomFieldFilters
            fields={customFieldSchema}
            value={customFields ?? {}}
            onChange={onCustomFieldsChange}
          />
        </FilterCard>
      ) : null}

      {hasAnyFilter ? (
        <button
          type="button"
          onClick={onClearAll}
          className="text-coral hover:text-coral/80 inline-flex items-center gap-1 px-2 text-xs font-medium"
        >
          <XIcon className="size-3.5" aria-hidden />
          {t('search.clear_all', 'مسح الكل')}
        </button>
      ) : null}
    </div>
  );
}

function FilterCard({
  title,
  children,
}: {
  title: string;
  children: React.ReactNode;
}) {
  return (
    <section className="border-ink-200 bg-card rounded-xl border p-4">
      <h3 className="text-ink-500 mb-3 text-[11px] font-bold uppercase tracking-wider">
        {title}
      </h3>
      {children}
    </section>
  );
}
