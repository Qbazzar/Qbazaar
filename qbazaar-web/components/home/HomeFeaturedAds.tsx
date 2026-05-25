'use client';

/**
 * Featured-ads strip rendered between the categories rail and the latest-ads
 * feed on the homepage. Hidden when the API returns an empty list so the
 * page never shows an editorial slot with no content.
 */
import { AdGrid } from '@/components/ads/AdGrid';
import { useFeaturedAdsQuery } from '@/lib/queries/ads';
import { t } from '@/lib/i18n/messages';

const MAX_FEATURED = 12;

export function HomeFeaturedAds() {
  const { data, isLoading, isError } = useFeaturedAdsQuery();

  if (isLoading) {
    return (
      <section
        className="mx-auto w-full max-w-6xl px-6 pb-4"
        aria-busy="true"
        aria-live="polite"
      >
        <SectionHeader />
        <div className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          {Array.from({ length: 4 }).map((_, i) => (
            <div
              key={i}
              className="bg-cream-200 h-64 animate-pulse rounded-xl"
              aria-hidden="true"
            />
          ))}
        </div>
      </section>
    );
  }

  if (isError || !data || data.length === 0) return null;

  return (
    <section className="mx-auto w-full max-w-6xl px-6 pb-4">
      <SectionHeader />
      <div className="mt-6">
        <AdGrid ads={data.slice(0, MAX_FEATURED)} />
      </div>
    </section>
  );
}

function SectionHeader() {
  return (
    <div className="mb-2">
      <p className="text-ink-500 text-xs font-bold uppercase tracking-[0.18em]">
        {t('ads.featured_section.kicker', 'اختيارنا')}
      </p>
      <h2 className="font-display mt-1 text-3xl italic text-ink-900 md:text-4xl">
        {t('ads.featured_section.title', 'إعلانات مختارة')}
      </h2>
    </div>
  );
}
