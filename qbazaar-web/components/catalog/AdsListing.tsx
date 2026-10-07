'use client';

import type { ReactNode } from 'react';
import { usePathname, useRouter, useSearchParams } from 'next/navigation';
import { SearchX } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import type { BreadcrumbItem } from '@/components/design-system/Breadcrumb';
import { EmptyState } from '@/components/design-system/EmptyState';
import { Icon } from '@/components/design-system/Icon';
import { Pagination } from '@/components/design-system/Pagination';
import { SaveSearchButton } from '@/components/search/SaveSearchButton';
import { formatNumber } from '@/lib/i18n/format';
import { getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';
import { useAdsListQuery } from '@/lib/queries/ads';
import { useCategoryStatsQuery, useCategoryTreeQuery } from '@/lib/queries/categories';
import { useQatarLocationsQuery } from '@/lib/queries/locations';
import { findCategoryBySlug } from '@/store/categories';
import { findLocationBySlug } from '@/store/locations';
import type { CategoryNode, SearchQueryParams } from '@/lib/api/types';

import { CatalogHeader, CatalogStats, todayStat, type CatalogStat } from './CatalogHeader';
import { CatalogLayout } from './CatalogLayout';
import { FilterSheet, FilterSidebar } from './filters/CatalogFilters';
import type { FilterGroupKey } from './filters/FilterPanel';
import { countActiveFilters, EMPTY_FILTERS, type FilterValues } from './filters/filter-values';
import { FEED_MAX_PAGE, listingSearch, parseListingQuery } from './listing-query';
import { ListingResults } from './ListingResults';
import { ListingToolbar } from './ListingToolbar';
import { LoadError } from './LoadError';
import { ResultsHeading } from './ResultsHeading';

export interface ListingHeading {
  title: string;
  breadcrumb: BreadcrumbItem[];
}

interface AdsListingProps {
  /** The category of `/c/[slug]`; `/ads` filters by `?category=` instead. */
  category?: { id: string; slug: string };
  /** Title and breadcrumb, given the category picked in the filters (`/ads`). */
  heading: (selected: CategoryNode | null) => ListingHeading;
}

type SlugFilter = 'none' | 'resolved' | 'pending' | 'failed' | 'unknown';

/** Where a slug of the URL stands on its way to the id `GET /ads` filters by. */
function slugFilter(slug: string | null, found: boolean, lookup: { isPending: boolean; isError: boolean }): SlugFilter {
  if (!slug) return 'none';
  if (found) return 'resolved';
  if (lookup.isError) return 'failed';
  return lookup.isPending ? 'pending' : 'unknown';
}

/**
 * Plain listing on `GET /ads` (no search engine): price and place filters,
 * sort, grid/list and pagination, all kept in the URL (69:467, 81:1629).
 */
export function AdsListing({ category, heading }: AdsListingProps) {
  const locale = getLocale();
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const query = parseListingQuery(searchParams);
  // A category page has its category fixed; a stray ?category= must not show up as a filter.
  const filters = category ? { ...query.filters, category: null } : query.filters;
  const hasFilters = countActiveFilters(filters) > 0;

  const treeQuery = useCategoryTreeQuery();
  const locationsQuery = useQatarLocationsQuery();
  const selected = filters.category ? findCategoryBySlug(treeQuery.data, filters.category) : null;
  const place = filters.location ? findLocationBySlug(locationsQuery.data, filters.location) : null;
  // The feed waits for every slug to resolve: without its id it would come back unfiltered.
  const slugFilters = [slugFilter(filters.category, Boolean(selected), treeQuery), slugFilter(filters.location, Boolean(place), locationsQuery)];
  const filtersReady = slugFilters.every((state) => state === 'none' || state === 'resolved');

  const adsQuery = useAdsListQuery(
    {
      category_id: category?.id ?? selected?.id,
      location_id: place?.id,
      price_min: filters.priceMin ?? undefined,
      price_max: filters.priceMax ?? undefined,
      sort: query.sort === 'latest' ? undefined : query.sort,
      page: query.page,
    },
    { enabled: filtersReady },
  );
  // Live counters for the unfiltered category: the tree's can be an hour old.
  const statsQuery = useCategoryStatsQuery(category && !hasFilters ? category.slug : undefined);

  const navigate = (search: string, replace = false) => {
    const href = `${pathname}${search}`;
    if (replace) router.replace(href, { scroll: false });
    else router.push(href, { scroll: false });
  };
  const applyFilters = (next: FilterValues) => navigate(listingSearch(searchParams, { filters: next }));
  const resetFilters = () => navigate(listingSearch(searchParams, { filters: EMPTY_FILTERS }));
  const retryLookups = () => {
    if (treeQuery.isError) treeQuery.refetch();
    if (locationsQuery.isError) locationsQuery.refetch();
  };

  const groups: FilterGroupKey[] = category ? ['price', 'location'] : ['category', 'price', 'location'];
  const panel = { groups, values: filters, onApply: applyFilters, onReset: resetFilters, categories: treeQuery.data, locations: locationsQuery.data };

  const saveParams: SearchQueryParams = {
    category_slug: category?.slug ?? filters.category ?? undefined,
    location_slug: filters.location ?? undefined,
    price_min: filters.priceMin ?? undefined,
    price_max: filters.priceMax ?? undefined,
    sort: query.sort,
  };

  const total = filtersReady ? adsQuery.data?.meta.total : undefined;
  const stats: CatalogStat[] = [];
  if (category && !hasFilters) {
    if (statsQuery.data) {
      const { ads_count: adsCount, today_count: todayCount } = statsQuery.data;
      stats.push(
        { value: formatNumber(adsCount, locale), label: tPlural('catalog.stats.ads', adsCount) },
        ...todayStat(todayCount, t('catalog.stats.today', 'اليوم'), locale),
      );
    }
  } else if (total !== undefined) {
    stats.push({ value: formatNumber(total, locale), label: tPlural('catalog.stats.ads', total) });
  }

  const { title, breadcrumb } = heading(selected);

  const emptyState = (
    <EmptyState
      headingLevel="h3"
      icon={<Icon icon={SearchX} size="lg" />}
      title={t('ads.empty.no_ads', 'لا توجد إعلانات هنا بعد.')}
      description={hasFilters ? t('catalog.empty.try_filters', 'جرّب تعديل الفلاتر أو تصفّح كل الأقسام.') : undefined}
      className="rounded-qb-2xl border border-qb-line bg-qb-surface"
      action={
        hasFilters ? (
          <Button variant="secondary" size="sm" onClick={resetFilters}>
            {t('search.reset_filters', 'إعادة ضبط الفلاتر')}
          </Button>
        ) : undefined
      }
    />
  );

  const loadError = (onRetry: () => void) => (
    <LoadError headingLevel="h3" title={t('catalog.empty.load_failed', 'تعذّر تحميل الإعلانات. حاول مرة أخرى.')} onRetry={onRetry} />
  );

  let results: ReactNode;
  if (slugFilters.includes('failed')) results = loadError(retryLookups);
  else if (adsQuery.isError) results = loadError(() => adsQuery.refetch());
  else if (slugFilters.includes('unknown')) results = emptyState;
  else {
    results = (
      <ListingResults
        ads={adsQuery.data?.data ?? []}
        view={query.view}
        isLoading={adsQuery.isPending}
        isFetching={adsQuery.isFetching && !adsQuery.isPending}
        label={title}
        empty={emptyState}
      />
    );
  }

  return (
    <CatalogLayout
      header={
        <CatalogHeader
          title={title}
          breadcrumb={breadcrumb}
          stats={<CatalogStats items={stats} />}
          actions={<SaveSearchButton params={saveParams} />}
        />
      }
      sidebar={<FilterSidebar {...panel} />}
      toolbar={
        <ListingToolbar
          filters={<FilterSheet {...panel} />}
          sort={{ value: query.sort, onChange: (sort) => navigate(listingSearch(searchParams, { sort })) }}
          view={{ value: query.view, onChange: (view) => navigate(listingSearch(searchParams, { view }), true) }}
          actions={<SaveSearchButton params={saveParams} variant="toolbar" />}
        />
      }
    >
      <ResultsHeading loading={adsQuery.isFetching} total={total} />
      {results}
      <Pagination
        page={query.page}
        totalPages={Math.min(adsQuery.data?.meta.last_page ?? 1, FEED_MAX_PAGE)}
        getHref={(page) => `${pathname}${listingSearch(searchParams, { page })}`}
        className="mt-6 qb-desktop:mt-8"
      />
    </CatalogLayout>
  );
}
