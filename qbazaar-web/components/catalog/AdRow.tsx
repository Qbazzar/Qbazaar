'use client';

import type { Ref } from 'react';

import { cn } from '@/lib/utils';
import type { AdSummary } from '@/lib/api/types';

import { CatalogAdCard } from './CatalogAdCard';
import { catalogBleed } from './layout';

interface AdRowProps {
  ads: AdSummary[];
  /** Accessible name of the list. */
  label: string;
  /**
   * `grid`: parent-category.html's rows, a swipeable row of 72 % cards on phones and auto-filled
   * 220 px columns from 601 px. `list`: a swipeable row that becomes a column of list cards from 1001 px.
   */
  desktop: 'grid' | 'list';
  /** Ads shown from 601 px (`grid`) or 1001 px (`list`); the swipeable row always holds all of them. */
  desktopLimit?: number;
  ref?: Ref<HTMLUListElement>;
}

const LAYOUTS = {
  grid: {
    list: 'flex snap-x snap-mandatory gap-3 overflow-x-auto pb-1.5 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden qb-tablet:[display:grid] qb-tablet:grid-cols-[repeat(auto-fill,minmax(220px,1fr))] qb-tablet:gap-[18px] qb-tablet:overflow-visible qb-tablet:pb-0',
    item: 'w-[72%] shrink-0 snap-start qb-tablet:w-auto',
    hidden: 'qb-tablet:hidden',
  },
  list: {
    list: cn(
      catalogBleed,
      'flex snap-x snap-mandatory gap-3 overflow-x-auto pb-2 [scrollbar-width:none] qb-tablet:gap-3.5 [&::-webkit-scrollbar]:hidden',
      'qb-desktop:mx-0 qb-desktop:flex-col qb-desktop:gap-6 qb-desktop:overflow-visible qb-desktop:px-0 qb-desktop:pb-0',
    ),
    item: 'w-[260px] shrink-0 snap-start qb-tablet:w-[310px] qb-desktop:w-auto',
    hidden: 'qb-desktop:hidden',
  },
} as const;

/** A row of listing cards that scrolls sideways on small screens (parent-category.html, the search's "Recommended for you"). */
export function AdRow({ ads, label, desktop, desktopLimit = ads.length, ref }: AdRowProps) {
  const layout = LAYOUTS[desktop];
  return (
    <ul ref={ref} aria-label={label} className={layout.list}>
      {ads.map((ad, index) => (
        <li key={ad.id} className={cn(layout.item, index >= desktopLimit && layout.hidden)}>
          {desktop === 'grid' ? (
            <CatalogAdCard ad={ad} layout="tile" />
          ) : (
            // The row becomes a column of list cards on desktop (655:55973); CSS shows one of the two.
            <>
              <CatalogAdCard ad={ad} layout="tile" className="qb-desktop:hidden" />
              <CatalogAdCard ad={ad} layout="row" className="hidden qb-desktop:flex" />
            </>
          )}
        </li>
      ))}
    </ul>
  );
}
