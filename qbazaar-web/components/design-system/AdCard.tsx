import type { ReactNode } from 'react';
import Link from 'next/link';
import { MapPin } from 'lucide-react';

import { cn } from '@/lib/utils';

import { Badge, Chip } from './Badge';
import { Icon } from './Icon';

export interface AdCardProps {
  href: string;
  title: string;
  /** The listing photo (or a placeholder); it fills the media box. */
  media: ReactNode;
  /** Formatted price, e.g. "QAR 285,000". */
  price?: string;
  /** Promotion label on the photo, e.g. "Top Ad". The grid layout puts the price there instead and shows this only for ads without a price. */
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
 * Listing card of the home sliders (grid) and the category list (list). The
 * title link is stretched over the whole card, so the card is one tab stop and
 * the favourite button is not nested inside a link.
 */
export function AdCard(props: AdCardProps) {
  const { layout = 'grid', className } = props;
  const isList = layout === 'list';

  return (
    <article
      className={cn(
        'relative flex overflow-hidden rounded-qb-xl border border-qb-line bg-qb-surface font-qb',
        'transition-[box-shadow,translate] duration-200 hover:-translate-y-[3px] hover:shadow-qb-hover motion-reduce:transition-none motion-reduce:hover:translate-y-0',
        'has-[a:focus-visible]:outline-2 has-[a:focus-visible]:outline-offset-2 has-[a:focus-visible]:outline-qb-brand-active',
        isList ? 'flex-col gap-[18px] p-4 qb-tablet:flex-row' : 'flex-col',
        className,
      )}
    >
      <AdMedia {...props} isList={isList} />
      <div className={cn('flex min-w-0 flex-1 flex-col', !isList && 'p-4')}>
        <AdHeading {...props} isList={isList} />
        {isList && props.description ? (
          <p className="mt-2 line-clamp-2 text-qb-body-sm text-qb-ink-faint">{props.description}</p>
        ) : null}
        <AdTags tags={props.tags} isList={isList} />
        <AdMeta location={props.location} postedAt={props.postedAt} isList={isList} />
      </div>
    </article>
  );
}

type PartProps = AdCardProps & { isList: boolean };

function AdMedia({ media, price, badge, favorite, isList }: PartProps) {
  const label = isList ? badge : (price ?? badge);
  return (
    <div
      className={cn(
        'relative shrink-0 overflow-hidden bg-qb-line [&>img]:size-full [&>img]:object-cover',
        isList ? 'h-40 rounded-qb-lg qb-tablet:w-[341px]' : 'h-[170px]',
      )}
    >
      {media}
      {label ? (
        <Badge tone="solid" size={isList ? 'sm' : 'md'} className={cn('absolute start-3 top-3', !isList && 'px-3')}>
          {label}
        </Badge>
      ) : null}
      {favorite ? <div className="absolute end-3 top-3 z-10">{favorite}</div> : null}
    </div>
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
      <Heading className="min-w-0 font-qb text-qb-h4 font-semibold tracking-normal text-qb-ink">{link}</Heading>
      {price ? <span className="shrink-0 text-qb-h4 font-semibold text-qb-ink">{price}</span> : null}
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
          <Chip size={isList ? 'md' : 'sm'}>{tag}</Chip>
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
        'mt-3 flex items-center gap-1.5 border-t border-qb-line pt-3 text-qb-ink-subtle',
        isList ? 'text-qb-caption' : 'text-qb-label',
      )}
    >
      <Icon icon={MapPin} size="sm" />
      <span className="truncate">{text}</span>
    </p>
  );
}
