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
 * The three cards of the reference's listing pages:
 * - `row`: a list result of category.html. The photo sits beside the text and
 *   wraps above it on phones.
 * - `grid`: a result of category.html's grid view (desktop only).
 * - `tile`: a card of parent-category.html's listing rows.
 */
export type CatalogCardLayout = 'row' | 'grid' | 'tile';

interface CatalogAdCardProps {
  ad: AdSummary;
  layout: CatalogCardLayout;
  /** First cards of the page load eagerly so they are not late for LCP. */
  eager?: boolean;
  className?: string;
}

const IMAGE_SIZES: Record<CatalogCardLayout, string> = {
  row: '(min-width: 1001px) 341px, (min-width: 601px) 245px, 100vw',
  grid: '320px',
  tile: '(min-width: 601px) 335px, 72vw',
};

const card = cn(
  'relative overflow-hidden rounded-qb-xl border border-qb-line bg-qb-surface font-qb',
  'transition-[box-shadow,translate] duration-200 hover:-translate-y-[3px] hover:shadow-qb-hover motion-reduce:transition-none motion-reduce:hover:translate-y-0',
  'has-[a:focus-visible]:outline-solid has-[a:focus-visible]:outline-2 has-[a:focus-visible]:outline-offset-2 has-[a:focus-visible]:outline-qb-brand-active',
);

/** The orange tag on the photo: the price, or "Top Ad" on the desktop list. */
const photoTag = 'absolute start-3 top-3';

/** Location and age under a hairline. */
const meta = 'flex items-center gap-1.5 border-t border-qb-line text-qb-ink-subtle';

const LAYOUTS = {
  row: {
    card: 'flex flex-wrap gap-[18px] p-4',
    media: 'min-h-[150px] min-w-[180px] flex-[1_1_200px] rounded-qb-lg',
    body: 'flex min-w-[240px] flex-[2_1_300px] flex-col',
    title: 'text-qb-h4 font-semibold text-qb-ink',
    description: 'mt-2.5 mb-3.5 text-qb-body-sm leading-[1.5] text-qb-ink-faint',
    tags: 'mb-auto gap-2',
    chip: 'md',
    meta: 'mt-4 pt-3 text-qb-caption',
    pin: 'size-[15px]',
    pinStroke: 1.6,
    heart: '[&_svg]:size-4',
  },
  grid: {
    card: 'flex h-full flex-col',
    media: 'h-[150px]',
    body: 'p-4',
    title: 'mb-4 text-qb-body leading-[1.3] font-medium text-qb-ink-title',
    description: 'mb-2.5 line-clamp-2 text-qb-label leading-[1.5] text-qb-ink-faint',
    tags: 'mb-3 gap-1.5',
    chip: 'sm',
    meta: 'pt-2.5 text-qb-label',
    pin: 'size-[13px]',
    pinStroke: 1.7,
    heart: '[&_svg]:size-[15px]',
  },
  tile: {
    card: 'flex h-full flex-col',
    media: 'h-[140px]',
    body: 'p-3.5',
    title: 'mb-2 line-clamp-2 text-qb-body-sm leading-[1.35] font-medium text-qb-ink-title',
    description: '',
    tags: 'mb-3 gap-1.5',
    chip: 'sm',
    meta: 'pt-2.5 text-qb-label',
    pin: 'size-[13px]',
    pinStroke: 1.7,
    heart: '[&_svg]:size-[15px]',
  },
} as const;

/**
 * Listing card of the catalog pages, set to the reference's card templates
 * and their typo.css overrides at every width. The title link is stretched
 * over the card, so the card is one tab stop; the favourite on the photo
 * comes after it in the DOM and sits above it.
 */
export function CatalogAdCard({ ad, layout, eager = false, className }: CatalogAdCardProps) {
  const locale = getLocale();
  const place = useQatarPlace(ad.location_slug);
  const styles = LAYOUTS[layout];
  const isRow = layout === 'row';
  const price = formatAdPrice(ad, locale);
  const description = layout !== 'tile' && ad.summary ? ad.summary : null;
  const tags = ad.spec_chips?.map((chip) => chip.value) ?? [];
  const where = [placeLabel(ad.location_slug, locale, place), formatAdAge(ad.published_at, locale)].filter(Boolean).join(' • ');

  return (
    <article className={cn(card, styles.card, className)}>
      <div className={cn('min-w-0', styles.body)}>
        {/* The title and the description each take the direction, and so the alignment, of their own words. */}
        {isRow ? (
          <div className="flex items-start justify-between gap-3.5">
            <CardTitle ad={ad} className={styles.title} />
            <span className="hidden shrink-0 text-qb-h4 font-semibold whitespace-nowrap text-qb-ink qb-tablet:block">{price}</span>
          </div>
        ) : (
          <CardTitle ad={ad} className={styles.title} />
        )}
        {description ? (
          <p dir="auto" className={styles.description}>
            {description}
          </p>
        ) : null}
        {tags.length ? (
          <ul className={cn('flex flex-wrap', styles.tags)}>
            {tags.map((tag, index) => (
              // Two yes/no specs can read the same.
              <li key={`${index}-${tag}`}>
                <Chip size={styles.chip}>{tag}</Chip>
              </li>
            ))}
          </ul>
        ) : null}
        {where ? (
          <p className={cn(meta, styles.meta)}>
            <Icon icon={MapPin} strokeWidth={styles.pinStroke} className={styles.pin} />
            {/* The age is worked out again at hydration, when it may have moved on by a minute. */}
            <span className="truncate" suppressHydrationWarning>
              {where}
            </span>
          </p>
        ) : null}
      </div>

      {/* Shown first, but after the title link in the DOM, so the heart on the photo is the second tab stop. */}
      <div className={cn('relative order-first shrink-0 overflow-hidden bg-qb-line', styles.media)}>
        <AdPhoto ad={ad} layout={layout} eager={eager} />
        {isRow ? <RowPhotoTag price={price} promoted={Boolean(ad.promotion)} /> : <PhotoTag className="inline-flex">{price}</PhotoTag>}
        <div className="absolute end-3 top-3 z-10">
          <FavoriteButton
            adId={ad.id}
            adTitle={ad.title}
            className={cn(
              'size-[30px] bg-qb-surface text-qb-ink-muted shadow-qb-soft ring-0 backdrop-blur-none hover:text-qb-brand focus-visible:ring-qb-brand-active [&_svg]:stroke-[1.5]',
              styles.heart,
            )}
          />
        </div>
      </div>
    </article>
  );
}

function CardTitle({ ad, className }: { ad: AdSummary; className: string }) {
  return (
    <h3 dir="auto" className={cn('min-w-0 font-qb tracking-normal', className)}>
      <Link href={`/ads/${ad.id}`} className="outline-none after:absolute after:inset-0 after:z-1">
        {ad.title}
      </Link>
    </h3>
  );
}

function AdPhoto({ ad, layout, eager }: { ad: AdSummary; layout: CatalogCardLayout; eager: boolean }) {
  const image = ad.primary_image;
  const url = image ? image.sizes.medium || image.url : null;
  if (!url) {
    return <span className="flex size-full items-center justify-center text-qb-micro text-qb-ink-subtle">{t('media.no_image', 'بدون صورة')}</span>;
  }
  return <Image src={url} alt="" fill sizes={IMAGE_SIZES[layout]} loading={eager ? 'eager' : 'lazy'} className="object-cover" />;
}

function PhotoTag({ className, children }: { className: string; children: string }) {
  return (
    <Badge tone="solid" size="sm" className={cn(photoTag, className)}>
      {children}
    </Badge>
  );
}

/**
 * The list card's photo tag: "Top Ad" on desktop for a promoted ad, none on
 * tablets, and the price on phones, where the price beside the title hides.
 */
function RowPhotoTag({ price, promoted }: { price: string; promoted: boolean }) {
  return (
    <>
      <PhotoTag className="inline-flex qb-tablet:hidden">{price}</PhotoTag>
      {promoted ? <PhotoTag className="hidden qb-desktop:inline-flex">{t('catalog.card.top_ad', 'إعلان مميز')}</PhotoTag> : null}
    </>
  );
}
