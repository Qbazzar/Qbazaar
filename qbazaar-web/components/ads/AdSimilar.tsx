'use client';

/**
 * Similar ads under the ad detail, in the place and style of the design's
 * "Another Ads From Seller" row. Hidden when the API has none; while loading,
 * placeholders hold the row's height.
 */
import { AdSummaryGridCard } from '@/components/ads/AdSummaryCards';
import { SectionHeader } from '@/components/design-system/SectionHeader';
import { useSimilarAdsQuery } from '@/lib/queries/ads';
import { t } from '@/lib/i18n/messages';

interface AdSimilarProps {
  adId: string;
  /** "View all" opens this category's listings. */
  categorySlug?: string;
  /** One row on desktop. */
  limit?: number;
}

const list = '[display:grid] grid-cols-1 gap-4 qb-tablet:grid-cols-2 qb-desktop:grid-cols-4 qb-desktop:gap-6';
const cardSizes = '(min-width: 1001px) 321px, (min-width: 601px) 50vw, 100vw';

export function AdSimilar({ adId, categorySlug, limit = 4 }: AdSimilarProps) {
  const { data, isPending, isError } = useSimilarAdsQuery(adId);

  if (isError || (!isPending && !data?.length)) return null;

  return (
    <section aria-labelledby="similar-ads-title" aria-busy={isPending || undefined} className="mt-8">
      <SectionHeader
        id="similar-ads-title"
        title={t('ads.similar_section.title')}
        action={categorySlug ? { href: `/c/${categorySlug}`, label: t('common.view_all') } : undefined}
        className="mb-6"
      />
      {isPending ? (
        <div aria-hidden="true" className={list}>
          {Array.from({ length: limit }, (_, index) => (
            <div key={index} className="h-[337px] animate-pulse rounded-qb-2xl bg-qb-fill" />
          ))}
        </div>
      ) : (
        <ul className={list}>
          {data.slice(0, limit).map((ad) => (
            <li key={ad.id}>
              <AdSummaryGridCard ad={ad} imageSizes={cardSizes} className="h-full shadow-qb-card" />
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
