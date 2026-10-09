'use client';

/**
 * A seller's active ads, newest first, 20 at a time with the peach "Loading
 * More Ads" button while more are left. Private sellers list them as rows
 * (seller-individual.html), companies as a grid of 230 px cards, three
 * across on desktop (seller-organization.html, polish.css).
 */
import { useEffect, useRef } from 'react';
import { Loader2Icon, PackageOpen } from 'lucide-react';

import { ListingCard } from '@/components/ads/ListingCard';
import { cardVariants } from '@/components/design-system/Card';
import { EmptyState } from '@/components/design-system/EmptyState';
import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { t } from '@/lib/i18n/messages';
import { useQatarLocationsQuery } from '@/lib/queries/locations';
import { useUserAdsQuery } from '@/lib/queries/users';
import { cn } from '@/lib/utils';

import { SellerAdRow } from './SellerAdRow';

interface SellerListingsProps {
  userId: string;
  sellerName: string;
  layout: 'rows' | 'grid';
}

const listClassName = {
  rows: 'flex flex-col gap-[18px]',
  grid: '[display:grid] grid-cols-[repeat(auto-fill,minmax(230px,1fr))] gap-5 qb-desktop:grid-cols-3',
};

/** The cards' own size, so nothing jumps when they arrive. */
const placeholderClassName = {
  rows: 'h-[168px] rounded-qb-xl',
  grid: 'h-[296px] rounded-qb-xl',
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
              <SellerAdRow ad={ad} headingLevel={headingLevel.rows} />
            ) : (
              <ListingCard ad={ad} variant="company" headingLevel={headingLevel.grid} />
            )}
          </li>
        ))}
      </ul>
      {hasNextPage ? (
        <div className="mt-6 flex justify-center">
          <button
            type="button"
            onClick={loadMore}
            aria-disabled={isFetchingNextPage || undefined}
            aria-busy={isFetchingNextPage || undefined}
            className={cn(
              'inline-flex cursor-pointer items-center gap-2 rounded-qb-lg border border-transparent bg-qb-brand-soft px-[30px] py-3.5 font-qb text-qb-body-sm font-semibold text-qb-brand transition-colors hover:border-qb-brand',
              focusRing,
            )}
          >
            {isFetchingNextPage ? <Loader2Icon className="size-4 animate-spin" aria-hidden /> : null}
            {t('users.profile.load_more')}
          </button>
        </div>
      ) : null}
    </>
  );
}
