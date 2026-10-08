'use client';

import Image from 'next/image';

import { FavoriteButton } from '@/components/ads/FavoriteButton';
import { AdRowCard } from '@/components/design-system/AdRowCard';
import { useQatarPlace } from '@/components/locations/QatarPlacesProvider';
import { formatAdAge, formatAdPrice, placeLabel } from '@/lib/ads/format';
import { getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import type { AdSummary } from '@/lib/api/types';

const IMAGE_SIZES = '(min-width: 1001px) 259px, (min-width: 601px) 220px, 100vw';

/** The design's white round heart on listing photos, as on `AdSummaryCard`. */
const favoriteClassName =
  'size-[30px] bg-qb-surface text-qb-ink shadow-qb-soft ring-0 backdrop-blur-none hover:text-qb-brand focus-visible:ring-qb-brand-active [&_svg]:size-[17px]';

interface AdSummaryRowCardProps {
  ad: AdSummary;
  headingLevel?: 'h2' | 'h3';
  className?: string;
}

/**
 * An `AdSummary` as the seller page's listing row: the same price, place and
 * age as `AdSummaryCard`, plus the description line and the spec chips.
 * The list mounts `useQatarLocationsQuery` once so the place names resolve.
 */
export function AdSummaryRowCard({ ad, headingLevel, className }: AdSummaryRowCardProps) {
  const locale = getLocale();
  const place = useQatarPlace(ad.location_slug);
  const image = ad.primary_image;

  return (
    <AdRowCard
      href={`/ads/${ad.id}`}
      title={ad.title}
      description={ad.summary || undefined}
      tags={ad.spec_chips?.map((chip) => chip.value)}
      price={formatAdPrice(ad, locale)}
      location={placeLabel(ad.location_slug, locale, place)}
      postedAt={formatAdAge(ad.published_at, locale)}
      headingLevel={headingLevel}
      className={className}
      media={
        image ? (
          <Image src={image.sizes.medium || image.url} alt="" fill sizes={IMAGE_SIZES} className="object-cover" />
        ) : (
          <span className="flex size-full items-center justify-center text-qb-micro text-qb-ink-secondary">
            {t('media.no_image')}
          </span>
        )
      }
      favorite={<FavoriteButton adId={ad.id} className={favoriteClassName} />}
    />
  );
}
