import type { ReactNode } from 'react';
import Link from 'next/link';
import { MapPin } from 'lucide-react';

import { cn } from '@/lib/utils';

import { Badge, Chip } from './Badge';
import { cardHover } from './card-hover';
import { Icon } from './Icon';

export interface AdRowCardProps {
  href: string;
  title: string;
  /** The listing photo (or a placeholder); it fills the media box. */
  media: ReactNode;
  /** Formatted price, e.g. "QAR 287,000". On the photo on phones, next to the title from 601 px. */
  price?: string;
  /** One or two lines from the description. */
  description?: string;
  /** Short spec chips: year, mileage, fuel... */
  tags?: string[];
  location?: string;
  /** Already formatted, e.g. "30 min ago". */
  postedAt?: string;
  /** Favourite toggle; rendered above the card link so it stays its own control. */
  favorite?: ReactNode;
  headingLevel?: 'h2' | 'h3';
  className?: string;
}

/**
 * Listing row of the seller page (136:1562, 548:42879): photo flush on the
 * start side, details beside it. Under 601 px it stacks into a card with the
 * price on the photo (625:31486). Like `AdCard`, the title link is stretched
 * over the card, so the whole row is one tab stop.
 */
export function AdRowCard({
  href,
  title,
  media,
  price,
  description,
  tags,
  location,
  postedAt,
  favorite,
  headingLevel: Heading = 'h3',
  className,
}: AdRowCardProps) {
  const meta = [location, postedAt].filter(Boolean).join(' • ');

  return (
    <article
      className={cn(
        'relative flex flex-col overflow-hidden rounded-qb-2xl border border-qb-line bg-qb-surface font-qb shadow-qb-card qb-tablet:flex-row',
        cardHover,
        'has-[a:focus-visible]:outline-2 has-[a:focus-visible]:outline-offset-2 has-[a:focus-visible]:outline-qb-brand-active',
        className,
      )}
    >
      <div
        className={cn(
          'relative h-[180px] shrink-0 overflow-hidden bg-qb-line [&>img]:size-full [&>img]:object-cover',
          'qb-tablet:h-auto qb-tablet:min-h-[205px] qb-tablet:w-[220px] qb-desktop:min-h-[218px] qb-desktop:w-[259px]',
        )}
      >
        {media}
        {price ? (
          <Badge tone="solid" className="absolute start-3 top-3 rounded-qb-xs text-qb-caption qb-tablet:hidden">
            {price}
          </Badge>
        ) : null}
        {favorite ? <div className="absolute end-3 top-3 z-10 qb-tablet:end-4 qb-tablet:top-4">{favorite}</div> : null}
      </div>

      <div className="flex min-w-0 flex-1 flex-col p-4 qb-tablet:px-4 qb-tablet:pt-7 qb-tablet:pb-5 qb-desktop:px-6 qb-desktop:pt-[30px]">
        <div className="flex items-start justify-between gap-4">
          <Heading className="line-clamp-2 min-w-0 text-qb-body leading-[1.25] font-medium tracking-normal text-qb-ink-body qb-desktop:text-qb-h5">
            <Link href={href} dir="auto" className="outline-none after:absolute after:inset-0">
              {title}
            </Link>
          </Heading>
          {price ? (
            <span className="hidden shrink-0 text-qb-body font-semibold text-qb-ink qb-tablet:block qb-desktop:text-qb-h4">
              {price}
            </span>
          ) : null}
        </div>
        {description ? (
          <p dir="auto" className="mt-3 line-clamp-2 text-qb-caption leading-[1.3] text-qb-ink-muted qb-desktop:max-w-[432px] qb-desktop:text-qb-body">
            {description}
          </p>
        ) : null}
        {tags?.length ? (
          <ul className="mt-3 flex flex-wrap gap-2">
            {tags.map((tag, index) => (
              // Two yes/no specs can read the same.
              <li key={`${index}-${tag}`}>
                <Chip size="sm" className="rounded-qb-pill">
                  {tag}
                </Chip>
              </li>
            ))}
          </ul>
        ) : null}
        {meta ? (
          <p className="mt-auto flex items-center gap-1.5 pt-4 text-qb-label text-qb-ink-subtle qb-tablet:text-qb-caption">
            <Icon icon={MapPin} size="sm" />
            <span className="truncate">{meta}</span>
          </p>
        ) : null}
      </div>
    </article>
  );
}
