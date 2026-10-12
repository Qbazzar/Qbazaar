'use client';

/**
 * Search results (492:20208): the query string is the single source of truth
 * (nuqs), the filters patch it and the URL feeds `GET /search`. With no match
 * the page switches to the "Search Not Found" layout with recommended ads
 * (655:55973).
 */
import { useMemo } from 'react';
import { useSearchParams } from 'next/navigation';
import { parseAsBoolean, parseAsFloat, parseAsInteger, parseAsString, parseAsStringEnum, useQueryStates } from 'nuqs';

import { EmptyState } from '@/components/design-system/EmptyState';
import { Icon } from '@/components/design-system/Icon';
import { SearchXIcon } from '@/components/design-system/icons';
import { Pagination } from '@/components/design-system/Pagination';
import { CatalogHeader, CatalogStats } from '@/components/catalog/CatalogHeader';
import { CatalogLayout } from '@/components/catalog/CatalogLayout';
import { FilterSheet, FilterSidebar } from '@/components/catalog/filters/CatalogFilters';
import type { FilterGroupKey } from '@/components/catalog/filters/FilterPanel';
import { filterableFields } from '@/components/catalog/filters/CustomFieldFilters';
import { AD_TYPES, CONDITIONS, SELLER_TYPES, SHIPPING_OPTIONS, countActiveFilters, sanePriceRange, type FilterValues } from '@/components/catalog/filters/filter-values';
import { SORT_MODES, VIEW_MODES } from '@/components/catalog/listing-query';
import { ListingResults } from '@/components/catalog/ListingResults';
import { ListingToolbar } from '@/components/catalog/ListingToolbar';
import { LoadError } from '@/components/catalog/LoadError';
import { useRequestResultsFocus } from '@/components/catalog/results-focus';
import { ResultsHeading } from '@/components/catalog/ResultsHeading';
import { SaveSearchButton } from '@/components/search/SaveSearchButton';
import { SearchNotFound } from '@/components/search/SearchNotFound';
import { decodeCustomFields, pruneCustomFields, readGeoFilter } from '@/components/search/search-params';
import { formatNumber } from '@/lib/i18n/format';
import { getLocale, localized } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';
import { ApiClientError } from '@/lib/api/auth';
import { useCategoryTreeQuery } from '@/lib/queries/categories';
import { useQatarLocationsQuery } from '@/lib/queries/locations';
import { useSearchQuery } from '@/lib/queries/search';
import { findCategoryBySlug } from '@/store/categories';
import type { SearchQueryParams } from '@/lib/api/types';

const PER_PAGE = 24;
const NO_GEO = { lat: null, lng: null, radius_km: null };

/**
 * After the keyword, the groups in the order of category.html's filter card
 * (Price, Offer, Seller, Direct Purchase, Shipping & Delivery, Cities), then
 * the filters only the search API has.
 */
const GROUPS: FilterGroupKey[] = ['keyword', 'price', 'adType', 'sellerType', 'buyNow', 'shipping', 'location', 'category', 'condition', 'customFields'];

export function SearchClient() {
  const locale = getLocale();
  const searchParams = useSearchParams();
  const [urlState, setUrlState] = useQueryStates(
    {
      q: parseAsString.withDefault(''),
      category_slug: parseAsString,
      location_slug: parseAsString,
      price_min: parseAsInteger,
      price_max: parseAsInteger,
      // The home hero's "Distance" filter: a point and a radius around it.
      lat: parseAsFloat,
      lng: parseAsFloat,
      radius_km: parseAsInteger,
      condition: parseAsStringEnum([...CONDITIONS]),
      ad_type: parseAsStringEnum([...AD_TYPES]),
      shipping: parseAsStringEnum([...SHIPPING_OPTIONS]),
      seller_type: parseAsStringEnum([...SELLER_TYPES]),
      buy_now: parseAsBoolean,
      sort: parseAsStringEnum([...SORT_MODES]).withDefault('latest'),
      page: parseAsInteger.withDefault(1),
      // Category custom-field filters, JSON-encoded so the dynamic keys ride a single URL param.
      cf: parseAsString,
      // A display preference: no history entry and no server round trip.
      view: parseAsStringEnum([...VIEW_MODES]).withDefault('list').withOptions({ history: 'replace', shallow: true }),
    },
    { history: 'push', shallow: false },
  );

  const requestResultsFocus = useRequestResultsFocus();
  const { data: categoryTree } = useCategoryTreeQuery();
  const { data: locationTree } = useQatarLocationsQuery();
  const selectedCategory = urlState.category_slug ? findCategoryBySlug(categoryTree, urlState.category_slug) : null;
  const decodedFields = useMemo(() => decodeCustomFields(urlState.cf), [urlState.cf]);
  // Only the fields of the chosen category apply (a stale `cf` key would be a 422); no category, no fields.
  const fieldKeys = useMemo(
    () => (urlState.category_slug ? (selectedCategory ? filterableFields(selectedCategory.custom_fields).map((field) => field.key) : null) : []),
    [urlState.category_slug, selectedCategory],
  );
  const customFields = useMemo(() => pruneCustomFields(decodedFields, fieldKeys), [decodedFields, fieldKeys]);
  // A reversed price pair would be a 422: it is no filter.
  const { priceMin, priceMax } = sanePriceRange(urlState.price_min, urlState.price_max);

  // The set filters only, by slug: the API resolves slugs itself, so the first
  // request does not wait for the trees and is not repeated once they load.
  // Saved searches keep these, without the page.
  const geo = useMemo(() => readGeoFilter(urlState.lat, urlState.lng, urlState.radius_km), [urlState.lat, urlState.lng, urlState.radius_km]);
  const searchFilters: SearchQueryParams = useMemo(() => {
    const params: SearchQueryParams = { sort: urlState.sort };
    if (urlState.q) params.q = urlState.q;
    if (urlState.category_slug) params.category_slug = urlState.category_slug;
    // A place and a circle would intersect, so the circle replaces the place.
    if (urlState.location_slug && !geo) params.location_slug = urlState.location_slug;
    if (geo) Object.assign(params, geo);
    if (priceMin !== null) params.price_min = priceMin;
    if (priceMax !== null) params.price_max = priceMax;
    if (urlState.condition) params.condition = urlState.condition;
    if (urlState.ad_type) params.ad_type = urlState.ad_type;
    if (urlState.shipping) params.shipping = urlState.shipping;
    if (urlState.seller_type) params.seller_type = urlState.seller_type;
    if (urlState.buy_now) params.buy_now = true;
    if (Object.keys(customFields).length > 0) params.custom_fields = customFields;
    return params;
  }, [urlState, customFields, geo, priceMin, priceMax]);

  const { data, isLoading, isFetching, isPlaceholderData, isError, error, refetch } = useSearchQuery({ ...searchFilters, page: urlState.page, per_page: PER_PAGE });

  const filters: FilterValues = {
    keyword: urlState.q,
    category: urlState.category_slug,
    location: urlState.location_slug,
    priceMin,
    priceMax,
    condition: urlState.condition,
    adType: urlState.ad_type,
    shipping: urlState.shipping,
    sellerType: urlState.seller_type,
    buyNow: urlState.buy_now === true,
    customFields,
  };

  const applyFilters = (next: FilterValues) => {
    // Choosing a place by hand leaves the distance filter behind.
    const placeChanged = next.location !== urlState.location_slug;
    // Back to page 1: the current page may not exist with the new filters.
    setUrlState({
      q: next.keyword || null,
      category_slug: next.category,
      location_slug: next.location,
      ...(placeChanged ? NO_GEO : {}),
      price_min: next.priceMin,
      price_max: next.priceMax,
      condition: next.condition,
      ad_type: next.adType,
      shipping: next.shipping,
      seller_type: next.sellerType,
      buy_now: next.buyNow || null,
      cf: Object.keys(next.customFields).length > 0 ? JSON.stringify(next.customFields) : null,
      page: 1,
    });
  };
  const resetFilters = () =>
    setUrlState({ ...NO_GEO, category_slug: null, location_slug: null, price_min: null, price_max: null, condition: null, ad_type: null, shipping: null, seller_type: null, buy_now: null, cf: null, page: 1 });

  const panel = { groups: GROUPS, values: filters, onApply: applyFilters, onReset: resetFilters, categories: categoryTree, locations: locationTree, facets: data?.facets, adFacets: isPlaceholderData ? undefined : data?.meta.facets };

  const categoryName = selectedCategory ? localized(selectedCategory.name, locale) : null;
  const title = urlState.q || categoryName || t('search.title_all', 'كل الإعلانات');
  const breadcrumb = [{ label: t('home.breadcrumb', 'الرئيسية'), href: '/' }, { label: title }];
  const total = data?.meta.total ?? 0;
  const lastPage = data?.meta.last_page ?? 1;
  const hasFilters = countActiveFilters(filters) > 0 || geo !== null;

  if (!isLoading && !isError && total === 0) {
    return (
      <SearchNotFound
        query={urlState.q}
        breadcrumb={breadcrumb}
        searching={isFetching}
        onSearch={(q) => {
          if (q === urlState.q) return;
          requestResultsFocus();
          setUrlState({ q: q || null, page: 1 });
        }}
        onReset={
          hasFilters
            ? () => {
                requestResultsFocus();
                resetFilters();
              }
            : undefined
        }
      />
    );
  }

  const pageHref = (page: number) => {
    const params = new URLSearchParams(searchParams.toString());
    if (page > 1) params.set('page', String(page));
    else params.delete('page');
    const search = params.toString();
    return search ? `/search?${search}` : '/search';
  };

  return (
    <CatalogLayout
      header={
        <CatalogHeader
          title={title}
          breadcrumb={breadcrumb}
          stats={<CatalogStats items={data ? [{ value: formatNumber(total, locale), label: tPlural('catalog.stats.results', total) }] : []} />}
          actions={<SaveSearchButton params={searchFilters} />}
        />
      }
      sidebar={<FilterSidebar {...panel} />}
      toolbar={
        <ListingToolbar
          filters={<FilterSheet {...panel} />}
          sort={{ value: urlState.sort, onChange: (sort) => setUrlState({ sort, page: 1 }) }}
          view={{ value: urlState.view, onChange: (view) => setUrlState({ view }) }}
          actions={<SaveSearchButton params={searchFilters} variant="toolbar" />}
        />
      }
    >
      <ResultsHeading loading={isFetching} total={data?.meta.total} />
      {isError ? (
        <LoadError headingLevel="h3" title={searchErrorMessage(error)} onRetry={() => refetch()} />
      ) : (
        <ListingResults
          ads={data?.data ?? []}
          view={urlState.view}
          isLoading={isLoading}
          isFetching={isFetching && !isLoading}
          label={title}
          empty={
            <EmptyState
              headingLevel="h3"
              icon={<Icon icon={SearchXIcon} size="lg" />}
              title={t('ads.empty.no_ads', 'لا توجد إعلانات هنا بعد.')}
              className="rounded-qb-xl border border-qb-line bg-qb-surface"
            />
          }
        />
      )}
      <Pagination page={urlState.page} totalPages={lastPage} getHref={pageHref} className="mt-6 qb-desktop:mt-8" />
    </CatalogLayout>
  );
}

function searchErrorMessage(error: unknown): string {
  const byCode = error instanceof ApiClientError ? t(`search.errors.${error.code.toLowerCase()}`, '') : '';
  return byCode || t('search.errors.load_failed', 'تعذّر تحميل نتائج البحث');
}
