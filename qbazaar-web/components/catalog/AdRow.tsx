'use client';

import type { Ref } from 'react';

import { AdSummaryCard } from '@/components/ads/AdSummaryCard';
import { cn } from '@/lib/utils';
import type { AdSummary } from '@/lib/api/types';

import { catalogBleed } from './layout';
import { ResponsiveListCard } from './ResponsiveListCard';

interface AdRowProps {
  ads: AdSummary[];
  /** Accessible name of the list. */
  label: string;
  /** From 1001 px the row becomes a three-column grid or a column of list cards. */
  desktop: 'grid' | 'list';
  /** Ads shown from 1001 px; the swipeable row always holds all of them. */
  desktopLimit?: number;
  ref?: Ref<HTMLUListElement>;
}

/**
 * A swipeable row of grid cards under 1001 px, reaching the screen edge, as on
 * the category overview (539:35503, 623:28688) and "Recommended for you"
 * (655:55238, 654:51147).
 */
export function AdRow({ ads, label, desktop, desktopLimit = ads.length, ref }: AdRowProps) {
  return (
    <ul
      ref={ref}
      aria-label={label}
      className={cn(
        catalogBleed,
        'flex snap-x snap-mandatory gap-3 overflow-x-auto pb-2 [scrollbar-width:none] qb-tablet:gap-3.5 [&::-webkit-scrollbar]:hidden',
        'qb-desktop:mx-0 qb-desktop:overflow-visible qb-desktop:px-0 qb-desktop:pb-0',
        desktop === 'grid' ? 'qb-desktop:[display:grid] qb-desktop:grid-cols-3 qb-desktop:gap-[17px]' : 'qb-desktop:flex-col qb-desktop:gap-6',
      )}
    >
      {ads.map((ad, index) => (
        <li key={ad.id} className={cn('w-[250px] shrink-0 snap-start qb-tablet:w-[310px] qb-desktop:w-auto', index >= desktopLimit && 'qb-desktop:hidden')}>
          {desktop === 'grid' ? <AdSummaryCard ad={ad} className="h-full" /> : <ResponsiveListCard ad={ad} />}
        </li>
      ))}
    </ul>
  );
}
