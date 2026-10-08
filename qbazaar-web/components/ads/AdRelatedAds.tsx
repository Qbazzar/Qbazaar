'use client';

/**
 * The rows of listing cards under the ad detail: the seller's other ads, in
 * the place of the design's "Another Ads From Seller" (88:776), then the
 * similar ads. Four cards on desktop, two on tablets, one per row on phones.
 * A row with nothing to show is left out; while it loads, placeholders of the
 * cards' size hold its place.
 */
import { useId } from 'react';

import { AdSummaryCard } from '@/components/ads/AdSummaryCard';
import { SectionHeader } from '@/components/design-system/SectionHeader';
import { t } from '@/lib/i18n/messages';
import { useSimilarAdsQuery } from '@/lib/queries/ads';
import { useQatarLocationsQuery } from '@/lib/queries/locations';
import { useUserAdsQuery } from '@/lib/queries/users';
import type { AdSummary } from '@/lib/api/types';

const ROW_LENGTH = 4;

const list = '[display:grid] grid-cols-1 gap-4 qb-tablet:grid-cols-2 qb-desktop:grid-cols-4 qb-desktop:gap-6';

interface RelatedAdsRowProps {
  title: string;
  viewAll?: { href: string; label: string };
  ads: AdSummary[] | undefined;
  isPending: boolean;
  isError: boolean;
}

function RelatedAdsRow({ title, viewAll, ads, isPending, isError }: RelatedAdsRowProps) {
  const titleId = useId();
  // Cards name their place from the locations tree in the store.
  useQatarLocationsQuery();

  if (isError || (!isPending && !ads?.length)) return null;

  return (
    <section aria-labelledby={titleId} aria-busy={isPending || undefined} className="mt-8">
      <SectionHeader id={titleId} title={title} action={viewAll} className="mb-6" />
      {isPending ? (
        <div className={list}>
          <span className="sr-only">{t('common.loading')}</span>
          {Array.from({ length: ROW_LENGTH }, (_, index) => (
            // The card's size with two title lines, its spec chips and the place line.
            <div
              key={index}
              aria-hidden="true"
              className="h-[328px] animate-pulse rounded-qb-xl bg-qb-fill motion-reduce:animate-none"
            />
          ))}
        </div>
      ) : (
        <ul className={list}>
          {ads?.slice(0, ROW_LENGTH).map((ad) => (
            <li key={ad.id}>
              <AdSummaryCard ad={ad} className="h-full shadow-qb-card" />
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}

/** The seller's other live ads, newest first; "View all" opens the seller page. */
export function AdSellerAds({ adId, sellerId }: { adId: string; sellerId: string }) {
  // The first page is the one the seller page lists first, so it is shared from the cache.
  const { data, isPending, isError } = useUserAdsQuery(sellerId);
  const others = data?.pages[0]?.data.filter((ad) => ad.id !== adId);

  return (
    <RelatedAdsRow
      title={t('ads.detail.seller_ads_title')}
      viewAll={{ href: `/u/${sellerId}`, label: t('common.view_all') }}
      ads={others}
      isPending={isPending}
      isError={isError}
    />
  );
}

/** Ads like this one; "View all" opens its category. */
export function AdSimilarAds({ adId, categorySlug }: { adId: string; categorySlug?: string }) {
  const { data, isPending, isError } = useSimilarAdsQuery(adId);

  return (
    <RelatedAdsRow
      title={t('ads.similar_section.title')}
      viewAll={categorySlug ? { href: `/c/${categorySlug}`, label: t('common.view_all') } : undefined}
      ads={data}
      isPending={isPending}
      isError={isError}
    />
  );
}
