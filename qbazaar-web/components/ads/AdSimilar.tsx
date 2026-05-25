'use client';

/**
 * Similar-ads strip rendered at the bottom of the ad-detail page.
 *
 * Hidden entirely when the API returns an empty list — we never paint a
 * stub. While the request is in flight we render skeleton tiles so the page
 * height doesn't jump when results arrive.
 */
import { AdGrid } from '@/components/ads/AdGrid';
import { useSimilarAdsQuery } from '@/lib/queries/ads';
import { t } from '@/lib/i18n/messages';

interface Props {
  adId: string;
  /** Cap how many to render. Defaults to 12 — matches the home feed. */
  limit?: number;
}

export function AdSimilar({ adId, limit = 12 }: Props) {
  const { data, isLoading, isError } = useSimilarAdsQuery(adId);

  if (isLoading) {
    return (
      <section
        className="border-ink-200 mt-6 border-t pt-6"
        aria-busy="true"
        aria-live="polite"
      >
        <SectionHeader />
        <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
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
    <section className="border-ink-200 mt-6 border-t pt-6">
      <SectionHeader />
      <div className="mt-4">
        <AdGrid ads={data.slice(0, limit)} />
      </div>
    </section>
  );
}

function SectionHeader() {
  return (
    <div>
      <p className="text-ink-500 text-xs font-bold uppercase tracking-[0.18em]">
        {t('ads.similar_section.kicker', 'مختار لك')}
      </p>
      <h2 className="font-display mt-1 text-2xl italic text-ink-900 md:text-3xl">
        {t('ads.similar_section.title', 'إعلانات مشابهة')}
      </h2>
    </div>
  );
}
