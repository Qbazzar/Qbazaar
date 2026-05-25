'use client';

/**
 * Client island for the `/ads` page — QBFront port (source: QBFront/search.html).
 *
 * Layout: breadcrumb · `.cat-page` (filters sidebar + main results) ·
 * pagination. Sidebar uses `.filters` + `.filter-group` cards from
 * QBFront; the main grid is `.grid.grid-3` of `.listing-card`s.
 */
import { useMemo } from 'react';
import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { CategoryTree } from '@/components/categories/CategoryTree';
import { LocationPicker } from '@/components/locations/LocationPicker';
import { QbfListingCard } from '@/components/ads/QbfListingCard';
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
    if (key !== 'page') params.delete('page');
    router.push(`/ads?${params.toString()}`);
  };

  const clearFilters = () => router.push('/ads');

  return (
    <main>
      <div className="container" style={{ paddingTop: 24, paddingBottom: 48 }}>
        <nav className="breadcrumbs">
          <Link href="/">{t('home.breadcrumb', 'الرئيسية')}</Link>
          <span>·</span>
          <span className="breadcrumbs__current">
            {t('ads.list.title', 'كل الإعلانات')}
          </span>
        </nav>

        <div className="cat-page__head">
          <div>
            <h1 className="cat-page__title">{t('ads.list.title', 'كل الإعلانات')}</h1>
            <p className="cat-page__meta">
              {data ? (
                <strong>
                  {t(
                    'ads.list.count',
                    { count: String(data.meta.total) },
                    `${data.meta.total} إعلان`,
                  )}
                </strong>
              ) : null}
              <span>{t('ads.list.subtitle', 'تصفّح آخر ما يبيعه جيرانك في قطر')}</span>
            </p>
          </div>
        </div>

        <div className="cat-page">
          {/* Filter sidebar */}
          <aside className="filters">
            <div className="filters__head">
              <h3 className="filters__title">
                {t('ads.list.filters', 'تصفية النتائج')}
              </h3>
              <button
                type="button"
                className="filters__reset"
                onClick={clearFilters}
              >
                {t('ads.list.reset', 'إعادة الضبط')}
              </button>
            </div>

            <div className="filter-group">
              <div className="filter-group__head">
                <span>{t('locations.pick', 'الموقع')}</span>
              </div>
              <div className="filter-group__body">
                <LocationPicker
                  value={locationSlug}
                  onChange={(slug) => updateParam('location', slug)}
                />
              </div>
            </div>

            <div className="filter-group">
              <div className="filter-group__head">
                <span>{t('categories.all', 'الأقسام')}</span>
              </div>
              <div className="filter-group__body">
                {tree ? (
                  <CategoryTree nodes={tree} activeSlug={categorySlug} />
                ) : (
                  <div className="bg-cream-200 h-32 animate-pulse rounded-lg" />
                )}
              </div>
            </div>
          </aside>

          {/* Results */}
          <section className="min-w-0">
            {isLoading ? (
              <div className="cat-listings" aria-busy="true">
                {Array.from({ length: 9 }).map((_, i) => (
                  <div
                    key={i}
                    className="listing-card animate-pulse"
                    style={{ height: 320 }}
                  />
                ))}
              </div>
            ) : isError || !data || data.data.length === 0 ? (
              <div className="empty-state">
                <div className="empty-state__title">
                  {t('ads.empty.no_ads', 'لا توجد إعلانات هنا بعد.')}
                </div>
                <div className="empty-state__sub">
                  {t('ads.empty.try_filters', 'جرّب تعديل الفلاتر أو استعراض كل الأقسام.')}
                </div>
              </div>
            ) : (
              <>
                <div className="cat-listings">
                  {data.data.map((ad) => (
                    <QbfListingCard key={ad.id} ad={ad} />
                  ))}
                </div>
                {data.meta.last_page > 1 ? (
                  <div className="pagination">
                    <button
                      type="button"
                      className="pagination__num"
                      onClick={() => updateParam('page', String(page - 1))}
                      disabled={page <= 1}
                      aria-label={t('ads.list.prev', 'السابق')}
                    >
                      ‹
                    </button>
                    {pageNumbers(page, data.meta.last_page).map((p, i) =>
                      p === 'gap' ? (
                        <span key={`gap-${i}`} className="pagination__gap">…</span>
                      ) : (
                        <button
                          key={p}
                          type="button"
                          className={`pagination__num ${p === page ? 'is-active' : ''}`}
                          onClick={() => updateParam('page', String(p))}
                        >
                          {p}
                        </button>
                      ),
                    )}
                    <button
                      type="button"
                      className="pagination__num"
                      onClick={() => updateParam('page', String(page + 1))}
                      disabled={page >= data.meta.last_page}
                      aria-label={t('ads.list.next', 'التالي')}
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
    </main>
  );
}

/**
 * Compact pagination strip: show first, last, the current page, and its two
 * neighbours, collapsing the rest into `…`.
 */
function pageNumbers(current: number, total: number): Array<number | 'gap'> {
  if (total <= 7) {
    return Array.from({ length: total }, (_, i) => i + 1);
  }
  const out: Array<number | 'gap'> = [1];
  const start = Math.max(2, current - 1);
  const end = Math.min(total - 1, current + 1);
  if (start > 2) out.push('gap');
  for (let i = start; i <= end; i++) out.push(i);
  if (end < total - 1) out.push('gap');
  out.push(total);
  return out;
}
