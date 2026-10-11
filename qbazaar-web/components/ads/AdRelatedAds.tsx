'use client';

/**
 * The rows of listing cards under the ad detail: the seller's other ads
 * (product.html's "Another Ads From Seller", up to two rows of four), then
 * the similar ads in the same cards. The grid fits as many 260 px columns as
 * the page holds: four at 1440, two at 744, one on phones. A row with
 * nothing to show is left out; while it loads, placeholders of the cards'
 * size hold its place.
 */
import { useId } from 'react';

import { ListingCard } from '@/components/ads/ListingCard';
import { SectionHeader } from '@/components/design-system/SectionHeader';
import { t } from '@/lib/i18n/messages';
import { useSimilarAdsQuery } from '@/lib/queries/ads';
import { useQatarLocationsQuery } from '@/lib/queries/locations';
import { useUserAdsQuery } from '@/lib/queries/users';
import type { AdSummary } from '@/lib/api/types';

const SELLER_ADS_SHOWN = 8;
const SIMILAR_ADS_SHOWN = 4;

const list = '[display:grid] grid-cols-[repeat(auto-fill,minmax(260px,1fr))] gap-5';

interface RelatedAdsRowProps {
  title: string;
  viewAll?: { href: string; label: string };
  ads: AdSummary[] | undefined;
  /** How many cards the row shows at most. */
  limit: number;
  isPending: boolean;
  isError: boolean;
}

function RelatedAdsRow({ title, viewAll, ads, limit, isPending, isError }: RelatedAdsRowProps) {
  const titleId = useId();
  // Cards name their place from the locations tree in the store.
  useQatarLocationsQuery();

  if (isError || (!isPending && !ads?.length)) return null;

  return (
    <section aria-labelledby={titleId} aria-busy={isPending || undefined} className="mt-10">
      {/* No gap, as in the reference: a phone keeps "Another Ads From Seller" on one line beside "View All". */}
      <SectionHeader id={titleId} title={title} action={viewAll} className="mb-[22px] items-center gap-0" />
      {isPending ? (
        <div className={list}>
          <span className="sr-only">{t('common.loading')}</span>
          {Array.from({ length: SIMILAR_ADS_SHOWN }, (_, index) => (
            // The card's size: the photo, two title lines and the place line.
            <div key={index} aria-hidden="true" className="h-[265px] animate-pulse rounded-qb-xl bg-qb-fill motion-reduce:animate-none" />
          ))}
        </div>
      ) : (
        <ul className={list}>
          {ads?.slice(0, limit).map((ad) => (
            <li key={ad.id}>
              <ListingCard ad={ad} variant="related" />
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
      viewAll={{ href: `/u/${sellerId}`, label: t('ads.detail.view_all') }}
      ads={others}
      limit={SELLER_ADS_SHOWN}
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
      viewAll={categorySlug ? { href: `/c/${categorySlug}`, label: t('ads.detail.view_all') } : undefined}
      ads={data}
      limit={SIMILAR_ADS_SHOWN}
      isPending={isPending}
      isError={isError}
    />
  );
}
