'use client';

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
import { listingSearch, parseListingQuery } from './listing-query';
import { ListingResults } from './ListingResults';
import { ListingToolbar } from './ListingToolbar';
import { LoadError } from './LoadError';

const PAGE_SIZE = 24;

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

  const { data: tree, isPending: treePending } = useCategoryTreeQuery();
  const { data: locations, isPending: locationsPending } = useQatarLocationsQuery();
  // A slug from the URL waits for its tree, or the first page would come back unfiltered.
  const resolvingFilters = (Boolean(filters.category) && treePending) || (Boolean(filters.location) && locationsPending);
  const selected = filters.category ? findCategoryBySlug(tree, filters.category) : null;
  const categoryId = category?.id ?? selected?.id;
  const locationId = filters.location ? findLocationBySlug(locations, filters.location)?.id : undefined;

  const adsQuery = useAdsListQuery(
    {
      category_id: categoryId,
      location_id: locationId,
      price_min: filters.priceMin ?? undefined,
      price_max: filters.priceMax ?? undefined,
      sort: query.sort === 'latest' ? undefined : query.sort,
      page: query.page,
      per_page: PAGE_SIZE,
    },
    { enabled: !resolvingFilters },
  );
  const statsQuery = useCategoryStatsQuery(category?.slug);

  const navigate = (search: string, replace = false) => {
    const href = `${pathname}${search}`;
    if (replace) router.replace(href, { scroll: false });
    else router.push(href, { scroll: false });
  };
  const applyFilters = (next: FilterValues) => navigate(listingSearch(searchParams, { filters: next }));
  const resetFilters = () => navigate(listingSearch(searchParams, { filters: EMPTY_FILTERS }));

  const groups: FilterGroupKey[] = category ? ['price', 'location'] : ['category', 'price', 'location'];
  const panel = { groups, values: filters, onApply: applyFilters, onReset: resetFilters, categories: tree, locations };

  const saveParams: SearchQueryParams = {
    category_slug: category?.slug ?? filters.category ?? undefined,
    location_slug: filters.location ?? undefined,
    price_min: filters.priceMin ?? undefined,
    price_max: filters.priceMax ?? undefined,
    sort: query.sort,
  };

  const stats: CatalogStat[] = [];
  const adsLabel = t('catalog.stats.ads', 'إعلان');
  if (category && statsQuery.data) {
    stats.push(
      { value: formatNumber(statsQuery.data.ads_count, locale), label: adsLabel },
      ...todayStat(statsQuery.data.today_count, t('catalog.stats.today', 'اليوم'), locale),
    );
  } else if (!category && adsQuery.data) {
    stats.push({ value: formatNumber(adsQuery.data.meta.total, locale), label: adsLabel });
  }

  const { title, breadcrumb } = heading(selected);
  const hasFilters = countActiveFilters(filters) > 0;

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
      <h2 className="sr-only">{t('catalog.results_heading', 'النتائج')}</h2>
      {adsQuery.isError ? (
        <LoadError headingLevel="h3" title={t('catalog.empty.load_failed', 'تعذّر تحميل الإعلانات. حاول مرة أخرى.')} onRetry={() => adsQuery.refetch()} />
      ) : (
        <ListingResults
          ads={adsQuery.data?.data ?? []}
          view={query.view}
          isLoading={adsQuery.isPending}
          isFetching={adsQuery.isFetching && !adsQuery.isPending}
          label={title}
          empty={
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
          }
        />
      )}
      <Pagination
        page={query.page}
        totalPages={adsQuery.data?.meta.last_page ?? 1}
        getHref={(page) => `${pathname}${listingSearch(searchParams, { page })}`}
        className="mt-6 qb-desktop:mt-8"
      />
    </CatalogLayout>
  );
}
