import type { ReactNode } from 'react';
import Link from 'next/link';
import { MapPin } from 'lucide-react';

import { cn } from '@/lib/utils';

import { Badge, Chip } from './Badge';
import { cardHover } from './card-hover';
import { Icon } from './Icon';

export interface AdCardProps {
  href: string;
  title: string;
  /** The listing photo (or a placeholder); it fills the media box. */
  media: ReactNode;
  /** Formatted price, e.g. "QAR 285,000". */
  price?: string;
  /**
   * Promotion label on the photo, e.g. "Top Ad". Where the price sits on the
   * photo (the grid layout, the list layout on phones), this shows only for
   * ads without a price.
   */
  badge?: string;
  /** Short spec chips: year, mileage, fuel... */
  tags?: string[];
  location?: string;
  /** Already formatted, e.g. "30 min ago". */
  postedAt?: string;
  /** Shown in the list layout only. */
  description?: string;
  /** Favourite toggle; rendered above the card link so it stays its own control. */
  favorite?: ReactNode;
  layout?: 'grid' | 'list';
  headingLevel?: 'h2' | 'h3';
  className?: string;
}

/**
 * The photo box of each layout. The list layout follows the category frames:
 * a stacked card on phones (623:30012), a flush 220 px photo beside the text
 * on tablets (544:38513) and a padded 341 × 160 photo on desktop.
 */
const MEDIA_BOX = {
  grid: 'h-[170px]',
  list: 'h-[180px] qb-tablet:h-auto qb-tablet:w-[220px] qb-desktop:h-40 qb-desktop:w-[341px] qb-desktop:rounded-qb-lg',
} as const;

/** The same boxes, absolutely placed, to pin the favourite button to the photo's corner. */
const FAVORITE_AREA = {
  grid: 'start-0 end-0 top-0 h-[170px]',
  list: 'start-0 end-0 top-0 h-[180px] qb-tablet:end-auto qb-tablet:bottom-0 qb-tablet:h-auto qb-tablet:w-[220px] qb-desktop:start-4 qb-desktop:top-4 qb-desktop:bottom-auto qb-desktop:h-40 qb-desktop:w-[341px]',
} as const;

/**
 * Listing card of the home sliders (grid) and the category list (list). The
 * title link is stretched over the whole card, so the card is one tab stop and
 * the favourite button is not nested inside a link. The favourite comes after
 * the title in the DOM, so the tab order reaches the ad before its button.
 */
export function AdCard(props: AdCardProps) {
  const { layout = 'grid', favorite, className } = props;
  const isList = layout === 'list';

  return (
    <article
      className={cn(
        'relative flex flex-col overflow-hidden rounded-qb-xl border border-qb-line bg-qb-surface font-qb',
        cardHover,
        'has-[a:focus-visible]:outline-solid has-[a:focus-visible]:outline-2 has-[a:focus-visible]:outline-offset-2 has-[a:focus-visible]:outline-qb-brand-active',
        isList && 'qb-tablet:flex-row qb-tablet:rounded-qb-2xl qb-desktop:gap-[18px] qb-desktop:rounded-qb-xl qb-desktop:p-4',
        className,
      )}
    >
      <AdMedia {...props} isList={isList} />
      <div className={cn('flex min-w-0 flex-1 flex-col p-4', isList && 'qb-tablet:py-6 qb-desktop:p-0')}>
        <AdHeading {...props} isList={isList} />
        {isList && props.description ? (
          <p className="mt-2.5 line-clamp-2 text-qb-caption text-qb-ink-faint qb-desktop:mt-2 qb-desktop:text-qb-body-sm">{props.description}</p>
        ) : null}
        <AdTags tags={props.tags} isList={isList} />
        <AdMeta location={props.location} postedAt={props.postedAt} isList={isList} />
      </div>
      {favorite ? (
        <div className={cn('pointer-events-none absolute z-10', FAVORITE_AREA[layout])}>
          {/* Tailwind 4 leaves buttons with the default cursor; the design's heart shows the pointer. */}
          <div className="pointer-events-auto absolute end-3 top-3 [&>button:enabled]:cursor-pointer">{favorite}</div>
        </div>
      ) : null}
    </article>
  );
}

type PartProps = AdCardProps & { isList: boolean };

function AdMedia({ media, price, badge, layout = 'grid', isList }: PartProps) {
  return (
    <div className={cn('relative shrink-0 overflow-hidden bg-qb-line [&>img]:size-full [&>img]:object-cover', MEDIA_BOX[layout])}>
      {media}
      <MediaLabels price={price} badge={badge} isList={isList} />
    </div>
  );
}

function MediaLabels({ price, badge, isList }: { price?: string; badge?: string; isList: boolean }) {
  const onPhoto = 'absolute start-3 top-3';
  const photoLabel = price ?? badge;
  if (!isList) {
    return photoLabel ? <Badge tone="solid" className={cn(onPhoto, 'px-3')}>{photoLabel}</Badge> : null;
  }
  // The list layout moves the price next to the title from tablets up, and the badge takes its place on the photo.
  return (
    <>
      {photoLabel && photoLabel !== badge ? (
        <Badge tone="solid" className={cn(onPhoto, 'px-3 qb-tablet:hidden')}>
          {photoLabel}
        </Badge>
      ) : null}
      {badge ? (
        <Badge tone="solid" size="sm" className={cn(onPhoto, photoLabel !== badge && 'hidden qb-tablet:inline-flex')}>
          {badge}
        </Badge>
      ) : null}
    </>
  );
}

function AdHeading({ href, title, price, headingLevel: Heading = 'h3', isList }: PartProps) {
  const link = (
    <Link href={href} dir="auto" className="outline-none after:absolute after:inset-0">
      {title}
    </Link>
  );
  if (!isList) {
    return <Heading className="line-clamp-2 min-h-[2.6em] font-qb text-qb-body leading-[1.3] font-medium tracking-normal text-qb-ink">{link}</Heading>;
  }
  return (
    <div className="flex items-start justify-between gap-5">
      <Heading className="min-w-0 font-qb text-qb-body font-semibold tracking-normal text-qb-ink-body qb-tablet:font-medium qb-desktop:text-qb-h4 qb-desktop:font-semibold qb-desktop:text-qb-ink">
        {link}
      </Heading>
      {price ? <span className="hidden shrink-0 text-qb-body font-semibold text-qb-ink qb-tablet:block qb-desktop:text-qb-h4">{price}</span> : null}
    </div>
  );
}

function AdTags({ tags, isList }: { tags?: string[]; isList: boolean }) {
  if (!tags?.length) return null;
  return (
    <ul className={cn('mt-3 flex flex-wrap', isList ? 'gap-2' : 'gap-1.5')}>
      {tags.map((tag, index) => (
        // Two yes/no specs can read the same.
        <li key={`${index}-${tag}`}>
          <Chip size="sm" className={cn(isList && 'qb-desktop:px-3 qb-desktop:py-[5px] qb-desktop:text-qb-label')}>
            {tag}
          </Chip>
        </li>
      ))}
    </ul>
  );
}

function AdMeta({ location, postedAt, isList }: { location?: string; postedAt?: string; isList: boolean }) {
  const text = [location, postedAt].filter(Boolean).join(' • ');
  if (!text) return null;
  return (
    <p
      className={cn(
        'mt-3 flex items-center gap-1.5 text-qb-label text-qb-ink-subtle',
        isList ? 'qb-tablet:text-qb-caption qb-desktop:border-t qb-desktop:border-qb-line qb-desktop:pt-3' : 'border-t border-qb-line pt-3',
      )}
    >
      <Icon icon={MapPin} size="sm" />
      {/* The age is worked out again at hydration, when it may have moved on by a minute. */}
      <span className="truncate" suppressHydrationWarning>
        {text}
      </span>
    </p>
  );
}
