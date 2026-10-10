'use client';

import Image from 'next/image';
import Link from 'next/link';
import { MapPin } from 'lucide-react';

import { FavoriteButton } from '@/components/ads/FavoriteButton';
import { Badge, Chip } from '@/components/design-system/Badge';
import { Icon } from '@/components/design-system/Icon';
import { useQatarPlace } from '@/components/locations/QatarPlacesProvider';
import { formatAdAge, formatAdPrice, placeLabel } from '@/lib/ads/format';
import { getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { AdSummary } from '@/lib/api/types';

/**
 * The three listing cards of the reference's product pages. They share the
 * r16 frame, the orange price chip on the photo and the "Doha • 30 min ago"
 * line, and differ in the photo height, the type and the extras:
 *
 *  - related: "Another Ads From Seller" (product.html): 150 px photo, 15 px
 *    title, no heart and no spec chips.
 *  - saved: the wishlist (wishlist.html): 170 px photo, 16 px title, the
 *    filled heart that unsaves the ad.
 *  - company: a company's ads (seller-organization.html): 150 px photo, the
 *    heart, the spec chips and a meta line without a rule above it.
 */
const VARIANTS = {
  related: {
    media: 'h-[150px]',
    price: 'px-3 text-qb-label',
    title: 'mb-2.5 min-h-10 text-qb-body-sm leading-[19.5px]',
    meta: 'border-t border-qb-line pt-2.5',
    favorite: null,
    tags: false,
  },
  saved: {
    media: 'h-[170px]',
    price: 'px-3 text-qb-label',
    title: 'mb-3 min-h-[42px] text-qb-body leading-[1.3]',
    meta: 'border-t border-qb-line pt-3',
    favorite: 'size-8 [&_svg]:size-[17px]',
    tags: false,
  },
  company: {
    media: 'h-[150px]',
    price: 'px-[11px] text-qb-micro',
    title: 'mb-3 min-h-[42px] text-qb-body leading-[1.3]',
    meta: '',
    favorite: 'size-[30px] [&_svg]:size-[15px]',
    tags: true,
  },
} as const;

export type ListingCardVariant = keyof typeof VARIANTS;

interface ListingCardProps {
  ad: AdSummary;
  variant: ListingCardVariant;
  /** The heart starts filled before the favourites store knows the ad (the wishlist). */
  favorited?: boolean;
  headingLevel?: 'h2' | 'h3';
  className?: string;
}

export function ListingCard({ ad, variant, favorited = false, headingLevel: Heading = 'h3', className }: ListingCardProps) {
  const locale = getLocale();
  const place = useQatarPlace(ad.location_slug);
  const look = VARIANTS[variant];
  const image = ad.primary_image;
  const meta = [placeLabel(ad.location_slug, locale, place), formatAdAge(ad.published_at, locale)].filter(Boolean).join(' • ');
  const tags = look.tags ? (ad.spec_chips?.map((chip) => chip.value) ?? []) : [];

  return (
    <article
      className={cn(
        'relative flex h-full flex-col overflow-hidden rounded-qb-xl border border-qb-line bg-qb-surface font-qb',
        'transition-[box-shadow,translate] duration-200 hover:-translate-y-[3px] hover:shadow-qb-hover motion-reduce:transition-none motion-reduce:hover:translate-y-0',
        'has-[a:focus-visible]:outline-2 has-[a:focus-visible]:outline-offset-2 has-[a:focus-visible]:outline-qb-brand-active has-[a:focus-visible]:outline-solid',
        className,
      )}
    >
      <div className={cn('relative shrink-0 overflow-hidden bg-qb-line', look.media)}>
        {image ? (
          <Image src={image.sizes.medium || image.url} alt="" fill sizes="(min-width: 601px) 340px, 100vw" className="object-cover" />
        ) : (
          <span className="flex size-full items-center justify-center text-qb-micro text-qb-ink-subtle">{t('media.no_image')}</span>
        )}
        <Badge tone="solid" className={cn('absolute start-3 top-3 py-[5px]', look.price)}>
          {formatAdPrice(ad, locale)}
        </Badge>
      </div>

      <div className="flex flex-1 flex-col p-4">
        <Heading className={cn('line-clamp-2 font-qb font-medium tracking-normal text-qb-ink', look.title)}>
          <Link href={`/ads/${ad.id}`} dir="auto" className="outline-none after:absolute after:inset-0">
            {ad.title}
          </Link>
        </Heading>
        {tags.length ? (
          <ul className="mb-3 flex flex-wrap gap-1.5">
            {tags.map((tag, index) => (
              // Two yes/no specs can read the same.
              <li key={`${index}-${tag}`}>
                <Chip size="sm">{tag}</Chip>
              </li>
            ))}
          </ul>
        ) : null}
        {meta ? (
          <p className={cn('mt-auto flex items-center gap-1.5 text-qb-label text-qb-ink-subtle', look.meta)}>
            <Icon icon={MapPin} className="size-[13px]" strokeWidth={1.7} />
            {/* The age is worked out again at hydration, when it may have moved on by a minute. */}
            <span className="truncate" suppressHydrationWarning>
              {meta}
            </span>
          </p>
        ) : null}
      </div>

      {look.favorite ? (
        <div className="absolute end-3 top-3 z-10">
          <FavoriteButton adId={ad.id} adTitle={ad.title} initialFavorited={favorited} className={look.favorite} />
        </div>
      ) : null}
    </article>
  );
}
