'use client';

/**
 * Client island for the `/ads` page.
 *
 * - Reads `category`, `location`, `page` from the URL.
 * - Resolves the slugs to ids via the cached category + location stores.
 * - Renders a sticky filter sidebar (CategoryTree + LocationPicker) on lg+
 *   and a grid of `AdCard`s with pagination underneath.
 */
import { useMemo } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { AdGrid } from '@/components/ads/AdGrid';
import { CategoryTree } from '@/components/categories/CategoryTree';
import { LocationPicker } from '@/components/locations/LocationPicker';
import { useAdsListQuery } from '@/lib/queries/ads';
import { useCategoryTreeQuery } from '@/lib/queries/categories';
import { useQatarLocationsQuery } from '@/lib/queries/locations';
import { findCategoryBySlug } from '@/store/categories';
import { findLocationBySlug } from '@/store/locations';
import { t } from '@/lib/i18n/messages';

const PER_PAGE = 24;

export function AdsListClient() {
  const router = useRouter();
  const search = useSearchParams();

  const categorySlug = search.get('category');
  const locationSlug = search.get('location');
  const page = Number(search.get('page') ?? '1') || 1;

  const { data: tree } = useCategoryTreeQuery();
  const { data: cities } = useQatarLocationsQuery();

  const categoryId = useMemo(() => {
    if (!tree || !categorySlug) return undefined;
    return findCategoryBySlug(tree, categorySlug)?.id;
  }, [tree, categorySlug]);

  const locationId = useMemo(() => {
    if (!cities || !locationSlug) return undefined;
    return findLocationBySlug(cities, locationSlug)?.id;
  }, [cities, locationSlug]);

  const { data, isLoading, isError } = useAdsListQuery({
    category_id: categoryId,
    location_id: locationId,
    page,
    per_page: PER_PAGE,
  });

  const updateParam = (key: string, value: string | null) => {
    const params = new URLSearchParams(search.toString());
    if (value) params.set(key, value);
    else params.delete(key);
    // Reset to page 1 whenever filters change.
    if (key !== 'page') params.delete('page');
    router.push(`/ads?${params.toString()}`);
  };

  return (
    <main className="bg-cream-50 min-h-svh">
      <div className="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6">
        <header className="mb-8">
          <h1 className="font-display text-4xl text-ink-900 md:text-5xl">
            {t('ads.list.title', 'كل الإعلانات')}
          </h1>
          <p className="text-ink-500 mt-2 text-sm">
            {t('ads.list.subtitle', 'تصفّح آخر ما يبيعه جيرانك في قطر')}
          </p>
        </header>

        <div className="grid gap-6 lg:grid-cols-[260px_minmax(0,1fr)]">
          <aside className="space-y-6 lg:sticky lg:top-6 lg:self-start">
            <div className="rounded-xl border border-ink-200 bg-card p-4">
              <h3 className="text-ink-500 mb-3 text-[11px] font-bold uppercase tracking-wider">
                {t('locations.pick', 'الموقع')}
              </h3>
              <LocationPicker
                value={locationSlug}
                onChange={(slug) => updateParam('location', slug)}
              />
            </div>
            <div className="rounded-xl border border-ink-200 bg-card p-4">
              <h3 className="text-ink-500 mb-3 text-[11px] font-bold uppercase tracking-wider">
                {t('categories.all', 'الأقسام')}
              </h3>
              {tree ? (
                <CategoryTree nodes={tree} activeSlug={categorySlug} />
              ) : (
                <div className="bg-cream-200 h-32 animate-pulse rounded-lg" />
              )}
            </div>
          </aside>

          <section className="min-w-0">
            {isLoading ? (
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                {Array.from({ length: 8 }).map((_, i) => (
                  <div
                    key={i}
                    className="bg-cream-200 h-64 animate-pulse rounded-xl"
                  />
                ))}
              </div>
            ) : isError || !data ? (
              <p className="text-ink-500 py-12 text-center text-sm">
                {t('common.error', 'تعذّر تحميل الإعلانات')}
              </p>
            ) : (
              <>
                <AdGrid ads={data.data} />
                {data.meta.last_page > 1 ? (
                  <nav className="mt-8 flex items-center justify-between">
                    <Button
                      type="button"
                      variant="outline"
                      onClick={() => updateParam('page', String(page - 1))}
                      disabled={page <= 1}
                    >
                      <ChevronRight className="size-4" />
                      {t('ads.list.prev', 'السابق')}
                    </Button>
                    <span className="text-ink-500 text-sm">
                      {t(
                        'ads.list.page_of',
                        { current: String(page), total: String(data.meta.last_page) },
                        `${page} / ${data.meta.last_page}`,
                      )}
                    </span>
                    <Button
                      type="button"
                      variant="outline"
                      onClick={() => updateParam('page', String(page + 1))}
                      disabled={page >= data.meta.last_page}
                    >
                      {t('ads.list.next', 'التالي')}
                      <ChevronLeft className="size-4" />
                    </Button>
                  </nav>
                ) : null}
              </>
            )}
          </section>
        </div>
      </div>
    </main>
  );
}
