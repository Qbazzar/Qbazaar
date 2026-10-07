'use client';

import Image from 'next/image';

import { FavoriteButton } from '@/components/ads/FavoriteButton';
import { AdCard } from '@/components/design-system/AdCard';
import { AdRowCard } from '@/components/design-system/AdRowCard';
import { formatAdAge, formatAdPriceLabel, labelFromSlug } from '@/lib/ads/display';
import { getLocale, localized } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { useQatarLocationsQuery } from '@/lib/queries/locations';
import { findLocationBySlug } from '@/store/locations';
import type { AdSummary } from '@/lib/api/types';

/** The design's white round heart on listing photos. */
const favoriteClassName =
  'size-[30px] bg-qb-surface text-qb-ink shadow-qb-soft ring-0 backdrop-blur-none hover:text-qb-brand focus-visible:ring-qb-brand-active [&_svg]:size-[17px]';

interface AdSummaryCardProps {
  ad: AdSummary;
  /** Rendered `sizes` of the photo, so the browser picks the right candidate. */
  imageSizes: string;
  headingLevel?: 'h2' | 'h3';
  className?: string;
}

function AdSummaryImage({ ad, imageSizes }: Pick<AdSummaryCardProps, 'ad' | 'imageSizes'>) {
  const image = ad.primary_image;
  if (!image) {
    return (
      <span className="flex size-full items-center justify-center text-qb-micro text-qb-ink-subtle">
        {t('media.no_image')}
      </span>
    );
  }
  return <Image src={image.sizes.medium || image.url} alt="" fill sizes={imageSizes} className="object-cover" />;
}

/**
 * List cards carry only the place slug, so its name in the page language
 * comes from the (cached) locations tree; the slug as words stands in until
 * the tree is loaded.
 */
function useCardText(ad: AdSummary) {
  const locale = getLocale();
  const { data: places } = useQatarLocationsQuery();
  const place = ad.location_slug ? findLocationBySlug(places, ad.location_slug) : null;

  return {
    price: formatAdPriceLabel(ad, locale),
    location: place ? localized(place.name, locale) : labelFromSlug(ad.location_slug),
    postedAt: formatAdAge(ad.published_at, locale),
  };
}

/** An `AdSummary` as the grid listing card (similar ads, company ads). */
export function AdSummaryGridCard({ ad, imageSizes, headingLevel, className }: AdSummaryCardProps) {
  const text = useCardText(ad);
  return (
    <AdCard
      href={`/ads/${ad.id}`}
      title={ad.title}
      media={<AdSummaryImage ad={ad} imageSizes={imageSizes} />}
      favorite={<FavoriteButton adId={ad.id} className={favoriteClassName} />}
      headingLevel={headingLevel}
      className={className}
      {...text}
    />
  );
}

/** An `AdSummary` as the seller page's listing row, with the description line under the title. */
export function AdSummaryRowCard({ ad, imageSizes, headingLevel, className }: AdSummaryCardProps) {
  const text = useCardText(ad);
  return (
    <AdRowCard
      href={`/ads/${ad.id}`}
      title={ad.title}
      description={ad.summary || undefined}
      media={<AdSummaryImage ad={ad} imageSizes={imageSizes} />}
      favorite={<FavoriteButton adId={ad.id} className={favoriteClassName} />}
      headingLevel={headingLevel}
      className={className}
      {...text}
    />
  );
}
