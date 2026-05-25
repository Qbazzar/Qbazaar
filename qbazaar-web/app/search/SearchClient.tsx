'use client';

/**
 * Search results client island.
 *
 * - Query string is the single source of truth (nuqs `useQueryStates`).
 * - The sidebar + sort dropdown patch the URL; the URL feeds the search query.
 * - Reuses `AdGrid` for the result list so the visual matches the home feed.
 * - The "Save search" button captures the current URL bag so it can be
 *   restored later from /account/saved-searches.
 */
import { useMemo } from 'react';
import Link from 'next/link';
import {
  useQueryStates,
  parseAsString,
  parseAsInteger,
  parseAsStringEnum,
} from 'nuqs';
import { SlidersHorizontalIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { QbfListingCard } from '@/components/ads/QbfListingCard';
import {
  FilterSidebar,
  type FilterValues,
} from '@/components/search/FilterSidebar';
import { SortDropdown } from '@/components/search/SortDropdown';
import { SaveSearchButton } from '@/components/search/SaveSearchButton';
import { useSearchQuery } from '@/lib/queries/search';
import { useCategoryTreeQuery } from '@/lib/queries/categories';
import { useQatarLocationsQuery } from '@/lib/queries/locations';
import { findCategoryBySlug } from '@/store/categories';
import { findLocationBySlug } from '@/store/locations';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import { ApiClientError } from '@/lib/api/auth';
import type {
  AdCondition,
  SearchQueryParams,
  SortMode,
} from '@/lib/api/types';

const PER_PAGE = 24;

const CONDITION_VALUES = ['new', 'like_new', 'used'] as const;
const SORT_VALUES: SortMode[] = ['latest', 'oldest', 'price_asc', 'price_desc'];

export function SearchClient() {
  const [urlState, setUrlState] = useQueryStates(
    {
      q: parseAsString.withDefault(''),
      category_slug: parseAsString,
      location_slug: parseAsString,
      price_min: parseAsInteger,
      price_max: parseAsInteger,
      condition: parseAsStringEnum<AdCondition>([...CONDITION_VALUES]),
      sort: parseAsStringEnum<SortMode>(SORT_VALUES).withDefault('latest'),
      page: parseAsInteger.withDefault(1),
    },
    {
      history: 'push',
      shallow: false,
    },
  );

  const { data: categoryTree } = useCategoryTreeQuery();
  const { data: locationTree } = useQatarLocationsQuery();

  const categoryId = useMemo(() => {
    if (!urlState.category_slug || !categoryTree) return undefined;
    return findCategoryBySlug(categoryTree, urlState.category_slug)?.id;
  }, [categoryTree, urlState.category_slug]);

  const locationId = useMemo(() => {
    if (!urlState.location_slug || !locationTree) return undefined;
    return findLocationBySlug(locationTree, urlState.location_slug)?.id;
  }, [locationTree, urlState.location_slug]);

  // Build the API params bag from the URL state — only attach non-empty entries.
  const apiParams: SearchQueryParams = useMemo(() => {
    const params: SearchQueryParams = {
      sort: urlState.sort,
      page: urlState.page,
      per_page: PER_PAGE,
    };
    if (urlState.q) params.q = urlState.q;
    if (urlState.category_slug) {
      params.category_slug = urlState.category_slug;
      if (categoryId) params.category_id = categoryId;
    }
    if (urlState.location_slug) {
      params.location_slug = urlState.location_slug;
      if (locationId) params.location_id = locationId;
    }
    if (urlState.price_min !== null) params.price_min = urlState.price_min;
    if (urlState.price_max !== null) params.price_max = urlState.price_max;
    if (urlState.condition) params.condition = urlState.condition;
    return params;
  }, [urlState, categoryId, locationId]);

  const { data, isLoading, isFetching, isError, error } =
    useSearchQuery(apiParams);

  const filterValues: FilterValues = {
    category_slug: urlState.category_slug,
    location_slug: urlState.location_slug,
    price_min: urlState.price_min,
    price_max: urlState.price_max,
    condition: urlState.condition,
  };

  const handleFilterPatch = (patch: Partial<FilterValues>) => {
    // Reset the page whenever any filter changes — otherwise we land on a
    // page-3 that no longer exists with the new filter set.
    setUrlState({ ...patch, page: 1 });
  };

  const handleClearAll = () => {
    setUrlState({
      category_slug: null,
      location_slug: null,
      price_min: null,
      price_max: null,
      condition: null,
      page: 1,
    });
  };

  const handleSort = (next: SortMode) => {
    setUrlState({ sort: next, page: 1 });
  };

  const handlePage = (next: number) => {
    setUrlState({ page: next });
    if (typeof window !== 'undefined') {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  };

  const total = data?.meta.total ?? 0;
  const lastPage = data?.meta.last_page ?? 1;

  const headline = urlState.q
    ? t('search.title_for', { query: urlState.q })
    : t('search.title_all', 'كل الإعلانات');

  return (
    <div className="container" style={{ paddingTop: 24, paddingBottom: 48 }}>
      <div className="cat-page__head">
        <div className="min-w-0">
          <h1 className="cat-page__title">{headline}</h1>
          {data ? (
            <p className="cat-page__meta">
              <strong>
                {t('search.results_count', { count: String(total) }, `${total} نتيجة`)}
              </strong>
            </p>
          ) : null}
        </div>
        <div className="cat-page__head-actions">
          <SortDropdown value={urlState.sort} onChange={handleSort} />
          <SaveSearchButton params={apiParams} />
        </div>
      </div>

      <div className="cat-page">
        <aside className="filters">
          <details className="mb-3 lg:hidden">
            <summary className="text-ink-700 flex cursor-pointer items-center gap-2 px-2 py-2 text-sm font-medium">
              <SlidersHorizontalIcon className="size-4" aria-hidden />
              {t('common.filters', 'الفلاتر')}
            </summary>
            <div className="px-2 pb-2">
              <FilterSidebar
                values={filterValues}
                onChange={handleFilterPatch}
                facets={data?.facets ?? null}
                onClearAll={handleClearAll}
              />
            </div>
          </details>
          <div className="hidden lg:block">
            <FilterSidebar
              values={filterValues}
              onChange={handleFilterPatch}
              facets={data?.facets ?? null}
              onClearAll={handleClearAll}
            />
          </div>
        </aside>

        <section className="min-w-0">
          {isLoading ? (
            <div className="cat-listings" aria-busy="true">
              {Array.from({ length: 9 }).map((_, index) => (
                <div
                  key={index}
                  className="listing-card animate-pulse"
                  style={{ height: 320 }}
                />
              ))}
            </div>
          ) : isError ? (
            <p className="text-destructive py-12 text-center text-sm">
              {error instanceof ApiClientError
                ? translateMaybeKey(`search.errors.${error.code.toLowerCase()}`) ||
                  translateMaybeKey('search.errors.load_failed') ||
                  error.message
                : t('search.errors.load_failed', 'تعذّر تحميل نتائج البحث')}
            </p>
          ) : !data || data.data.length === 0 ? (
            <EmptyState onReset={handleClearAll} />
          ) : (
            <>
              <div
                className={`cat-listings ${
                  isFetching ? 'opacity-70 transition-opacity' : 'transition-opacity'
                }`}
              >
                {data.data.map((ad) => (
                  <QbfListingCard key={ad.id} ad={ad} />
                ))}
              </div>
              {lastPage > 1 ? (
                <div className="pagination">
                  <button
                    type="button"
                    className="pagination__num"
                    onClick={() => handlePage(urlState.page - 1)}
                    disabled={urlState.page <= 1}
                    aria-label={t('search.prev', 'السابق')}
                  >
                    ‹
                  </button>
                  <span className="pagination__gap">
                    {t(
                      'search.page_of',
                      { current: String(urlState.page), total: String(lastPage) },
                      `${urlState.page} / ${lastPage}`,
                    )}
                  </span>
                  <button
                    type="button"
                    className="pagination__num"
                    onClick={() => handlePage(urlState.page + 1)}
                    disabled={urlState.page >= lastPage}
                    aria-label={t('search.next', 'التالي')}
                  >
                    ›
                  </button>
                </div>
              ) : null}
            </>
          )}
        </section>
      </div>
    </div>
  );
}

function EmptyState({ onReset }: { onReset: () => void }) {
  return (
    <div className="empty-state">
      <div className="empty-state__title">
        {t('search.no_results', 'لم نعثر على نتائج')}
      </div>
      <div className="empty-state__sub">
        {t('search.try_other_filters', 'جرّب تغيير الفلاتر أو استعرض كل الأقسام.')}
      </div>
      <div className="mt-5 flex items-center justify-center gap-2">
        <Button
          type="button"
          variant="outline"
          onClick={onReset}
          className="rounded-full"
        >
          {t('search.reset_filters', 'إعادة ضبط الفلاتر')}
        </Button>
        <Link href="/ads" className="btn btn--primary btn--pill">
          {t('home.hero.cta_browse', 'تصفّح الإعلانات')}
        </Link>
      </div>
    </div>
  );
}
