'use client';

/**
 * A seller's active ads, newest first, 20 at a time with "Load more ads".
 * Private sellers list them as rows (136:1562), companies as a card grid
 * (145:1063); both stack into cards on phones.
 */
import { useEffect, useRef } from 'react';
import { Loader2Icon, PackageOpen } from 'lucide-react';

import { AdSummaryCard } from '@/components/ads/AdSummaryCard';
import { AdSummaryRowCard } from '@/components/ads/AdSummaryRowCard';
import { Button } from '@/components/design-system/Button';
import { cardVariants } from '@/components/design-system/Card';
import { EmptyState } from '@/components/design-system/EmptyState';
import { Icon } from '@/components/design-system/Icon';
import { t } from '@/lib/i18n/messages';
import { useQatarLocationsQuery } from '@/lib/queries/locations';
import { useUserAdsQuery } from '@/lib/queries/users';
import { cn } from '@/lib/utils';

interface SellerListingsProps {
  userId: string;
  sellerName: string;
  layout: 'rows' | 'grid';
}

const listClassName = {
  rows: 'flex flex-col gap-4 qb-tablet:gap-6',
  grid: '[display:grid] grid-cols-1 gap-4 qb-tablet:grid-cols-2 qb-tablet:gap-5 qb-desktop:grid-cols-3 qb-desktop:gap-x-4 qb-desktop:gap-y-6',
};

/** The cards' own size (a description line, the spec chips and the place line), so nothing jumps when they arrive. */
const placeholderClassName = {
  rows: 'h-[356px] rounded-qb-2xl qb-tablet:h-[207px] qb-desktop:h-[220px]',
  grid: 'h-[328px] rounded-qb-xl',
};

// Rows sit under the "Active Listings" heading; the company grid has none above it.
const headingLevel = { rows: 'h3', grid: 'h2' } as const;

export function SellerListings({ userId, sellerName, layout }: SellerListingsProps) {
  const { data, isPending, isError, fetchNextPage, hasNextPage, isFetchingNextPage } = useUserAdsQuery(userId);
  const listRef = useRef<HTMLUListElement>(null);
  const focusIndex = useRef<number | null>(null);
  // Cards name their place from the locations tree in the store.
  useQatarLocationsQuery();
  const ads = data?.pages.flatMap((page) => page.data) ?? [];

  // "Load more" may be gone after the last page, so the first new ad takes the focus.
  useEffect(() => {
    if (focusIndex.current === null || isFetchingNextPage) return;
    const index = focusIndex.current;
    focusIndex.current = null;
    listRef.current?.children[index]?.querySelector<HTMLElement>('a[href]')?.focus();
  }, [ads.length, isFetchingNextPage]);

  const loadMore = () => {
    if (isFetchingNextPage) return;
    focusIndex.current = ads.length;
    void fetchNextPage();
  };

  if (isPending) {
    return (
      <div aria-busy="true" className={listClassName[layout]}>
        <span className="sr-only">{t('common.loading')}</span>
        {Array.from({ length: 3 }, (_, index) => (
          <div
            key={index}
            aria-hidden="true"
            className={cn('animate-pulse bg-qb-fill motion-reduce:animate-none', placeholderClassName[layout])}
          />
        ))}
      </div>
    );
  }

  if (isError) {
    return (
      <p role="alert" className="text-qb-caption text-qb-danger">
        {t('common.error')}
      </p>
    );
  }

  if (ads.length === 0) {
    return (
      <EmptyState
        headingLevel={headingLevel[layout]}
        icon={<Icon icon={PackageOpen} size="lg" />}
        title={t('users.profile.ads_empty_title')}
        description={t('users.profile.ads_empty_body', { name: sellerName })}
        className={cn(cardVariants({ large: true, padding: 'none' }))}
      />
    );
  }

  return (
    <>
      <ul ref={listRef} className={listClassName[layout]}>
        {ads.map((ad) => (
          <li key={ad.id}>
            {layout === 'rows' ? (
              <AdSummaryRowCard ad={ad} headingLevel={headingLevel.rows} />
            ) : (
              <AdSummaryCard ad={ad} headingLevel={headingLevel.grid} className="h-full shadow-qb-card" />
            )}
          </li>
        ))}
      </ul>
      {hasNextPage ? (
        <div className="mt-6 flex justify-center">
          <Button
            size="sm"
            onClick={loadMore}
            aria-disabled={isFetchingNextPage || undefined}
            aria-busy={isFetchingNextPage || undefined}
            className="h-[37px] px-[13px] text-qb-body focus-visible:outline-solid"
          >
            {isFetchingNextPage ? <Loader2Icon className="animate-spin" aria-hidden /> : null}
            {t('users.profile.load_more')}
          </Button>
        </div>
      ) : null}
    </>
  );
}
