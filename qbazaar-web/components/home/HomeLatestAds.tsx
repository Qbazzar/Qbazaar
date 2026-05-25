'use client';

/**
 * Home page client island — first page of `/ads` rendered as a QBFront
 * `.grid.grid-4` of `.listing-card`s.
 */
import { QbfListingCard } from '@/components/ads/QbfListingCard';
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
      <div className="grid grid-4" aria-busy="true">
        {Array.from({ length: 8 }).map((_, i) => (
          <div
            key={i}
            className="listing-card animate-pulse"
            style={{ height: 320 }}
            aria-hidden="true"
          />
        ))}
      </div>
    );
  }
  if (isError || !data || data.data.length === 0) {
    return (
      <p className="text-muted py-8 text-center text-sm">
        {t('ads.empty.no_ads', 'لا توجد إعلانات هنا بعد.')}
      </p>
    );
  }
  return (
    <div className="grid grid-4">
      {data.data.map((ad) => (
        <QbfListingCard key={ad.id} ad={ad} />
      ))}
    </div>
  );
}
