'use client';

import Image from 'next/image';
import Link from 'next/link';
import { MapPin } from 'lucide-react';

import { FavoriteButton } from '@/components/ads/FavoriteButton';
import { Icon } from '@/components/design-system/Icon';
import { useQatarPlace } from '@/components/locations/QatarPlacesProvider';
import { formatAdAge, formatAdPrice, placeLabel } from '@/lib/ads/format';
import { getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { AdSummary } from '@/lib/api/types';

interface SellerAdRowProps {
  ad: AdSummary;
  headingLevel?: 'h2' | 'h3';
  className?: string;
}

/**
 * A listing row of seller-individual.html: a padded r16 card with the photo
 * inset (r12) on the start side and the title, the price and the place line
 * beside it. Photo and text share the row 1 : 2 and wrap into a stacked card
 * when the row is too narrow for both (phones). Like the other cards, the
 * title link is stretched over the row, so the whole row is one tab stop.
 */
export function SellerAdRow({ ad, headingLevel: Heading = 'h3', className }: SellerAdRowProps) {
  const locale = getLocale();
  const place = useQatarPlace(ad.location_slug);
  const image = ad.primary_image;
  const meta = [placeLabel(ad.location_slug, locale, place), formatAdAge(ad.published_at, locale)].filter(Boolean).join(' • ');

  return (
    <article
      className={cn(
        'relative flex flex-wrap gap-[18px] rounded-qb-xl border border-qb-line bg-qb-surface p-4 font-qb',
        'transition-[box-shadow,translate] duration-200 hover:-translate-y-[3px] hover:shadow-qb-hover motion-reduce:transition-none motion-reduce:hover:translate-y-0',
        'has-[a:focus-visible]:outline-2 has-[a:focus-visible]:outline-offset-2 has-[a:focus-visible]:outline-qb-brand-active has-[a:focus-visible]:outline-solid',
        className,
      )}
    >
      <div className="relative min-h-[130px] min-w-[160px] flex-[1_1_180px] overflow-hidden rounded-qb-lg bg-qb-line">
        {image ? (
          <Image src={image.sizes.medium || image.url} alt="" fill sizes="(min-width: 1001px) 340px, (min-width: 601px) 240px, 100vw" className="object-cover" />
        ) : (
          <span className="flex size-full items-center justify-center text-qb-micro text-qb-ink-subtle">{t('media.no_image')}</span>
        )}
        <div className="absolute end-3 top-3 z-10">
          <FavoriteButton adId={ad.id} adTitle={ad.title} className="size-[30px] [&_svg]:size-4" />
        </div>
      </div>

      <div className="flex min-w-[220px] flex-[2_1_280px] flex-col">
        <div className="mb-3.5 flex items-start justify-between gap-3.5">
          <Heading className="min-w-0 text-qb-h4 font-medium tracking-normal text-qb-ink-title">
            <Link href={`/ads/${ad.id}`} dir="auto" className="outline-none after:absolute after:inset-0">
              {ad.title}
            </Link>
          </Heading>
          <span className="shrink-0 text-qb-h4 font-medium whitespace-nowrap text-qb-ink-title">{formatAdPrice(ad, locale)}</span>
        </div>
        {meta ? (
          <p className="mt-auto flex items-center gap-1.5 border-t border-qb-line pt-3 text-qb-caption text-qb-ink-subtle">
            <Icon icon={MapPin} className="size-[15px]" strokeWidth={1.6} />
            {/* The age is worked out again at hydration, when it may have moved on by a minute. */}
            <span className="truncate" suppressHydrationWarning>
              {meta}
            </span>
          </p>
        ) : null}
      </div>
    </article>
  );
}
