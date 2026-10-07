'use client';

import Image from 'next/image';

import { FavoriteButton } from '@/components/ads/FavoriteButton';
import { AdCard } from '@/components/design-system/AdCard';
import { formatAdAge, formatAdPrice, placeLabel } from '@/lib/ads/format';
import { getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import type { AdSummary } from '@/lib/api/types';
import { useLocationsStore } from '@/store/locations';

interface AdSummaryCardProps {
  ad: AdSummary;
  layout?: 'grid' | 'list';
  /** First cards of the page load eagerly so they are not late for LCP. */
  eager?: boolean;
  className?: string;
}

const IMAGE_SIZES = {
  grid: '(min-width: 601px) 310px, 100vw',
  list: '(min-width: 601px) 341px, 100vw',
} as const;

/** An `AdSummary` from the API shown as the design-system listing card. */
export function AdSummaryCard({ ad, layout = 'grid', eager = false, className }: AdSummaryCardProps) {
  const locale = getLocale();
  const place = useLocationsStore((state) => state.findBySlug(ad.location_slug));
  const image = ad.primary_image;
  const imageUrl = image ? image.sizes.medium || image.url : null;

  return (
    <AdCard
      href={`/ads/${ad.id}`}
      title={ad.title}
      layout={layout}
      price={formatAdPrice(ad, locale)}
      tags={ad.spec_chips?.map((chip) => chip.value)}
      location={placeLabel(ad.location_slug, locale, place)}
      postedAt={formatAdAge(ad.published_at, locale)}
      className={className}
      media={
        imageUrl ? (
          <Image src={imageUrl} alt="" fill sizes={IMAGE_SIZES[layout]} loading={eager ? 'eager' : 'lazy'} className="object-cover" />
        ) : (
          <span className="flex size-full items-center justify-center text-qb-micro text-qb-ink-secondary">
            {t('media.no_image', 'بدون صورة')}
          </span>
        )
      }
      favorite={
        <FavoriteButton
          adId={ad.id}
          className="size-8 bg-qb-surface text-qb-ink-muted shadow-qb-soft ring-0 backdrop-blur-none hover:text-qb-brand focus-visible:ring-qb-brand-active [&_svg]:size-[17px]"
        />
      }
    />
  );
}
