'use client';

/**
 * Featured-ads strip rendered between the categories rail and the latest-ads
 * feed on the homepage. Hidden when the API returns an empty list so the
 * page never shows an editorial slot with no content.
 *
 * Renders as a QBFront `.grid.grid-4` of `.listing-card`s with a `.section-header`.
 */
import Link from 'next/link';
import { QbfListingCard } from '@/components/ads/QbfListingCard';
import { useFeaturedAdsQuery } from '@/lib/queries/ads';
import { t } from '@/lib/i18n/messages';

const MAX_FEATURED = 12;

export function HomeFeaturedAds() {
  const { data, isLoading, isError } = useFeaturedAdsQuery();

  if (isLoading) {
    return (
      <section className="container" style={{ paddingTop: 48 }} aria-busy="true" aria-live="polite">
        <SectionHeader />
        <div className="grid grid-4">
          {Array.from({ length: 4 }).map((_, i) => (
            <div
              key={i}
              className="listing-card animate-pulse"
              style={{ height: 320 }}
              aria-hidden="true"
            />
          ))}
        </div>
      </section>
    );
  }

  if (isError || !data || data.length === 0) return null;

  return (
    <section className="container" style={{ paddingTop: 48 }}>
      <SectionHeader />
      <div className="grid grid-4">
        {data.slice(0, MAX_FEATURED).map((ad) => (
          <QbfListingCard key={ad.id} ad={ad} />
        ))}
      </div>
    </section>
  );
}

function SectionHeader() {
  return (
    <div className="section-header">
      <div>
        <h2 className="section-header__title">
          {t('ads.featured_section.title', 'إعلانات مختارة')}
        </h2>
        <p className="section-header__sub">
          {t('ads.featured_section.subtitle', 'أحدث الإعلانات المنتقاة لك')}
        </p>
      </div>
      <Link href="/ads" className="section-header__action">
        {t('home.sections.view_more', 'عرض الكل')}
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round">
          <path d="M5 12h14M13 6l6 6-6 6" />
        </svg>
      </Link>
    </div>
  );
}
