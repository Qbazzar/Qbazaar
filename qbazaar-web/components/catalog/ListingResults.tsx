'use client';

import type { ReactNode } from 'react';

import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { AdSummary } from '@/lib/api/types';

import { CatalogAdCard } from './CatalogAdCard';
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

/** One list card per line; the grid view has three columns, 18 px apart as the list cards (category.html). */
const rowList = 'flex flex-col gap-[18px]';
const gridList = `${rowList} qb-desktop:[display:grid] qb-desktop:grid-cols-3`;

/**
 * Result cards in the list or grid layout of category.html. The view toggle
 * is a desktop control: below 1001 px both views show the list cards, whose
 * photo wraps above the text on phones.
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
                <CatalogAdCard {...card} layout="row" className="qb-desktop:hidden" />
                <CatalogAdCard {...card} layout="grid" className="hidden qb-desktop:flex" />
              </>
            ) : (
              <CatalogAdCard {...card} layout="row" />
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
        <div key={index} aria-hidden="true" className={cn(card, 'h-[384px] qb-tablet:h-[249px] qb-desktop:h-[194px]', view === 'grid' && 'qb-desktop:h-[331px]')} />
      ))}
    </div>
  );
}
