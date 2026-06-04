'use client';

/**
 * Client island for the category detail page.
 *
 * Responsibilities:
 *  1. Pull the full category tree (so we can render the breadcrumb +
 *     resolve the active node + display children).
 *  2. Pull per-category filters/fields/stats — driven off the slug.
 *  3. Render the layout: breadcrumb → header → ads grid + pagination →
 *     children grid → filters sidebar.
 *  4. Call `notFound()` once the tree resolves and the slug is absent.
 *
 * Ads listing uses `GET /api/v1/ads?category_id=…&location_id=…` (does not
 * depend on Meilisearch). Location filter resolves slug → id via the
 * locations store; custom per-category filters remain UI-only until the
 * backend `/ads` endpoint accepts dynamic field filters.
 */
import { useMemo, useState } from 'react';
import { notFound } from 'next/navigation';
import { AdGrid } from '@/components/ads/AdGrid';
import { CategoryGrid } from '@/components/categories/CategoryGrid';
import { CategoryBreadcrumb } from '@/components/categories/CategoryBreadcrumb';
import {
  CategoryFilters,
  type FilterValues,
} from '@/components/categories/CategoryFilters';
import { LocationPicker } from '@/components/locations/LocationPicker';
import {
  useCategoryFiltersQuery,
  useCategoryStatsQuery,
  useCategoryTreeQuery,
} from '@/lib/queries/categories';
import { useAdsListQuery } from '@/lib/queries/ads';
import { useQatarLocationsQuery } from '@/lib/queries/locations';
import { findCategoryBySlug } from '@/store/categories';
import { findLocationBySlug } from '@/store/locations';
import { localized, getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import type { CategoryNode } from '@/lib/api/types';

const PAGE_SIZE = 12;

interface Props {
  slug: string;
}

export function CategoryDetailClient({ slug }: Props) {
  const locale = getLocale();
  const treeQuery = useCategoryTreeQuery();
  const filtersQuery = useCategoryFiltersQuery(slug);
  const statsQuery = useCategoryStatsQuery(slug);

  const node: CategoryNode | null = useMemo(
    () => findCategoryBySlug(treeQuery.data ?? null, slug),
    [treeQuery.data, slug],
  );

  const [filterValues, setFilterValues] = useState<FilterValues>({});
  const [locationSlug, setLocationSlug] = useState<string | null>(null);
  const [page, setPage] = useState(1);

  // Locations tree — used to resolve the picker slug → location_id.
  const locationsQuery = useQatarLocationsQuery();
  const locationId = useMemo(() => {
    if (!locationSlug) return undefined;
    const loc = findLocationBySlug(locationsQuery.data ?? null, locationSlug);
    return loc?.id;
  }, [locationsQuery.data, locationSlug]);

  // Reset to page 1 whenever the location filter changes.
  const onLocationChange = (next: string | null) => {
    setLocationSlug(next);
    setPage(1);
  };

  // Drive the ads request only once we have a category id.
  const adsQuery = useAdsListQuery(
    node?.id
      ? {
          category_id: node.id,
          location_id: locationId,
          page,
          per_page: PAGE_SIZE,
        }
      : {},
  );

  if (treeQuery.isLoading) {
    return <DetailSkeleton />;
  }

  // Tree resolved but slug missing → real 404.
  if (treeQuery.data && !node) {
    notFound();
  }

  if (treeQuery.isError || !treeQuery.data) {
    return (
      <main className="mx-auto w-full max-w-6xl px-4 py-10 sm:px-6 sm:py-14">
        <p className="text-destructive text-sm">
          {t('categories.errors.not_found', 'لم نعثر على هذا القسم')}
        </p>
      </main>
    );
  }

  // After the guards above, node is guaranteed non-null.
  const category = node!;
  const subAdsCount =
    statsQuery.data?.sub_ads_count ?? statsQuery.data?.ads_count ?? null;

  return (
    <main className="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 sm:py-12">
      <CategoryBreadcrumb slug={slug} tree={treeQuery.data} className="mb-6" />

      <header className="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="font-display text-4xl leading-[1.05] tracking-tight md:text-6xl">
            <em className="not-italic md:italic">
              {localized(category.name, locale)}
            </em>
          </h1>
          {category.description ? (
            <p className="text-ink-700 mt-3 max-w-xl text-sm leading-relaxed">
              {localized(category.description, locale)}
            </p>
          ) : null}
        </div>
        {subAdsCount !== null ? (
          <span className="bg-coral/15 text-terracotta inline-flex items-center rounded-full px-3 py-1 text-xs font-medium">
            {t(
              'categories.ads_count',
              { count: formatNumber(subAdsCount, locale) },
              `${formatNumber(subAdsCount, locale)} إعلان`,
            )}
          </span>
        ) : null}
      </header>

      <div className="grid grid-cols-1 gap-8 lg:grid-cols-[1fr_280px]">
        <section className="space-y-8">
          <AdsSection
            isLoading={adsQuery.isLoading}
            isError={adsQuery.isError}
            ads={adsQuery.data?.data ?? []}
            currentPage={page}
            lastPage={adsQuery.data?.meta?.last_page ?? 1}
            onPage={setPage}
            onRetry={() => adsQuery.refetch()}
          />

          {category.children.length > 0 ? (
            <section aria-labelledby="subcats-heading">
              <h2
                id="subcats-heading"
                className="text-ink-900 mb-3 text-base font-medium"
              >
                {t('categories.subcategories', 'الأقسام الفرعية')}
              </h2>
              <CategoryGrid categories={category.children} />
            </section>
          ) : null}
        </section>

        <aside className="space-y-6">
          <section aria-labelledby="location-heading">
            <h2
              id="location-heading"
              className="text-ink-900 mb-3 text-base font-medium"
            >
              {t('locations.pick', 'الموقع')}
            </h2>
            <LocationPicker
              value={locationSlug}
              onChange={onLocationChange}
            />
          </section>

          <section aria-labelledby="filters-heading">
            <h2
              id="filters-heading"
              className="text-ink-900 mb-3 text-base font-medium"
            >
              {t('common.filters', 'الفلاتر')}
            </h2>
            {filtersQuery.isLoading ? (
              <div className="space-y-3">
                {Array.from({ length: 3 }).map((_, i) => (
                  <div
                    key={i}
                    className="bg-cream-200/60 h-9 animate-pulse rounded-lg"
                    aria-hidden
                  />
                ))}
              </div>
            ) : (
              <CategoryFilters
                filters={filtersQuery.data ?? []}
                values={filterValues}
                onChange={setFilterValues}
              />
            )}
          </section>
        </aside>
      </div>
    </main>
  );
}

interface AdsSectionProps {
  isLoading: boolean;
  isError: boolean;
  ads: import('@/lib/api/types').AdSummary[];
  currentPage: number;
  lastPage: number;
  onPage: (next: number) => void;
  onRetry: () => void;
}

function AdsSection({
  isLoading,
  isError,
  ads,
  currentPage,
  lastPage,
  onPage,
  onRetry,
}: AdsSectionProps) {
  if (isLoading) {
    return (
      <ul
        className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        aria-busy="true"
      >
        {Array.from({ length: 8 }).map((_, i) => (
          <li
            key={i}
            className="bg-cream-200/60 h-64 animate-pulse rounded-xl"
            aria-hidden
          />
        ))}
      </ul>
    );
  }

  if (isError) {
    return (
      <div className="border-destructive/30 bg-destructive/5 rounded-xl border p-6 text-center">
        <p className="text-destructive mb-3 text-sm">
          {t(
            'ads.errors.load_failed',
            'تعذّر تحميل الإعلانات. حاول مرة أخرى.',
          )}
        </p>
        <button
          type="button"
          onClick={onRetry}
          className="text-destructive text-sm font-medium underline"
        >
          {t('common.retry', 'إعادة المحاولة')}
        </button>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <AdGrid
        ads={ads}
        empty={t(
          'categories.no_ads_in_category',
          'لا توجد إعلانات في هذا القسم حتى الآن.',
        )}
      />

      {lastPage > 1 ? (
        <nav
          aria-label={t('common.pagination', 'التنقل بين الصفحات')}
          className="flex items-center justify-center gap-3 pt-2"
        >
          <button
            type="button"
            onClick={() => onPage(currentPage - 1)}
            disabled={currentPage <= 1}
            className="border-ink-200 text-ink-700 hover:bg-cream-100 inline-flex h-9 min-w-9 items-center justify-center rounded-lg border px-3 text-sm transition disabled:cursor-not-allowed disabled:opacity-40"
            aria-label={t('common.previous_page', 'السابق')}
          >
            ‹
          </button>
          <span className="text-ink-700 text-sm" aria-live="polite">
            {currentPage} / {lastPage}
          </span>
          <button
            type="button"
            onClick={() => onPage(currentPage + 1)}
            disabled={currentPage >= lastPage}
            className="border-ink-200 text-ink-700 hover:bg-cream-100 inline-flex h-9 min-w-9 items-center justify-center rounded-lg border px-3 text-sm transition disabled:cursor-not-allowed disabled:opacity-40"
            aria-label={t('common.next_page', 'التالي')}
          >
            ›
          </button>
        </nav>
      ) : null}
    </div>
  );
}

function DetailSkeleton() {
  return (
    <main className="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 sm:py-12">
      <div className="bg-cream-200/60 mb-6 h-5 w-48 animate-pulse rounded" />
      <div className="bg-cream-200/60 mb-8 h-12 w-72 animate-pulse rounded" />
      <div className="grid grid-cols-1 gap-8 lg:grid-cols-[1fr_280px]">
        <div className="bg-cream-200/60 h-48 animate-pulse rounded-2xl" />
        <div className="space-y-3">
          {Array.from({ length: 4 }).map((_, i) => (
            <div
              key={i}
              className="bg-cream-200/60 h-9 animate-pulse rounded-lg"
            />
          ))}
        </div>
      </div>
    </main>
  );
}

function formatNumber(count: number, locale: 'ar' | 'en'): string {
  const lang = locale === 'ar' ? 'ar-EG' : 'en-US';
  return new Intl.NumberFormat(lang).format(count);
}
