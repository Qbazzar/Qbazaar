'use client';

/**
 * Home page client island — first page of `/ads` rendered through AdGrid.
 *
 * Lives next to the hero so it's the first thing scrolling visitors see.
 * Errors degrade gracefully into the same empty state the grid uses.
 */
import { AdGrid } from '@/components/ads/AdGrid';
import { useAdsListQuery } from '@/lib/queries/ads';
import { t } from '@/lib/i18n/messages';

const PER_PAGE = 12;

export function HomeLatestAds() {
  const { data, isLoading, isError } = useAdsListQuery({
    page: 1,
    per_page: PER_PAGE,
  });

  if (isLoading) {
    return (
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        {Array.from({ length: 8 }).map((_, i) => (
          <div
            key={i}
            className="bg-cream-200 h-64 animate-pulse rounded-xl"
            aria-hidden="true"
          />
        ))}
      </div>
    );
  }
  if (isError || !data) {
    return (
      <p className="text-ink-500 py-8 text-center text-sm">
        {t('ads.empty.no_ads', 'لا توجد إعلانات هنا بعد.')}
      </p>
    );
  }
  return <AdGrid ads={data.data} />;
}
