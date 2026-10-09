'use client';

import type { ReactNode } from 'react';

import { AdSummaryCard } from '@/components/ads/AdSummaryCard';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { AdSummary } from '@/lib/api/types';

import type { ViewMode } from './listing-query';

interface ListingResultsProps {
  ads: AdSummary[];
  view: ViewMode;
  isLoading: boolean;
  /** A new page or filter is loading while the previous results stay on screen. */
  isFetching?: boolean;
  /** Shown when the list is empty. */
  empty: ReactNode;
  /** Accessible name of the result list. */
  label: string;
}

const EAGER_CARDS = 2;
const SKELETON_CARDS = 4;

/** One card per row under 1001 px: stacked on phones (623:30012), photo beside the text on tablets (544:38513). */
const belowDesktop = 'flex flex-col gap-4';
const gridList = `${belowDesktop} qb-desktop:[display:grid] qb-desktop:grid-cols-3 qb-desktop:gap-[19px]`;
const rowList = `${belowDesktop} qb-desktop:gap-6`;

/**
 * Result cards in the list (69:467) or grid (81:1629) layout. The view toggle
 * is a desktop control: below 1001 px both views show the list cards, which
 * stack on phones and lie flat on tablets.
 */
export function ListingResults({ ads, view, isLoading, isFetching = false, empty, label }: ListingResultsProps) {
  if (isLoading) return <ResultsSkeleton view={view} />;
  if (!ads.length) return <>{empty}</>;

  return (
    <ul
      aria-label={label}
      aria-busy={isFetching || undefined}
      className={cn(view === 'grid' ? gridList : rowList, 'transition-opacity motion-reduce:transition-none', isFetching && 'opacity-70')}
    >
      {ads.map((ad, index) => {
        const card = { ad, eager: index < EAGER_CARDS };
        return (
          <li key={ad.id}>
            {view === 'grid' ? (
              <>
                <AdSummaryCard {...card} layout="list" className="qb-desktop:hidden" />
                <AdSummaryCard {...card} layout="grid" className="hidden h-full qb-desktop:flex" />
              </>
            ) : (
              <AdSummaryCard {...card} layout="list" />
            )}
          </li>
        );
      })}
    </ul>
  );
}

function ResultsSkeleton({ view }: { view: ViewMode }) {
  const card = 'animate-pulse rounded-qb-xl border border-qb-line bg-qb-surface motion-reduce:animate-none';
  return (
    <div aria-busy="true" aria-live="polite" className={view === 'grid' ? gridList : rowList}>
      <span className="sr-only">{t('common.loading', 'جاري التحميل…')}</span>
      {Array.from({ length: SKELETON_CARDS }, (_, index) => (
        <div key={index} aria-hidden="true" className={cn(card, 'h-[330px] qb-tablet:h-[194px]', view === 'grid' && 'qb-desktop:h-[270px]')} />
      ))}
    </div>
  );
}
