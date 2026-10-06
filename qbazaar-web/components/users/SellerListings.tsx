'use client';

/**
 * A seller's active ads, newest first, 20 at a time with "Load more ads".
 * Private sellers list them as rows (136:1562), companies as a card grid
 * (145:1063); both stack into cards on phones.
 */
import { Loader2Icon, PackageOpen } from 'lucide-react';

import { AdSummaryGridCard, AdSummaryRowCard } from '@/components/ads/AdSummaryCards';
import { Button } from '@/components/design-system/Button';
import { EmptyState } from '@/components/design-system/EmptyState';
import { Icon } from '@/components/design-system/Icon';
import { t } from '@/lib/i18n/messages';
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

const imageSizes = {
  rows: '(min-width: 1001px) 259px, (min-width: 601px) 220px, 100vw',
  grid: '(min-width: 1001px) 279px, (min-width: 601px) 50vw, 100vw',
};

export function SellerListings({ userId, sellerName, layout }: SellerListingsProps) {
  const { data, isPending, isError, fetchNextPage, hasNextPage, isFetchingNextPage } = useUserAdsQuery(userId);
  const ads = data?.pages.flatMap((page) => page.data) ?? [];

  if (isPending) {
    return (
      <div aria-busy="true" className={listClassName[layout]}>
        {Array.from({ length: 3 }, (_, index) => (
          <div
            key={index}
            className={cn(
              'animate-pulse rounded-qb-2xl bg-qb-fill',
              layout === 'rows' ? 'h-[360px] qb-tablet:h-[205px] qb-desktop:h-[218px]' : 'h-[337px]',
            )}
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
        headingLevel="h3"
        icon={<Icon icon={PackageOpen} size="lg" />}
        title={t('users.profile.ads_empty_title')}
        description={t('users.profile.ads_empty_body', { name: sellerName })}
        className="rounded-qb-2xl border border-qb-line bg-qb-surface"
      />
    );
  }

  const Card = layout === 'rows' ? AdSummaryRowCard : AdSummaryGridCard;

  return (
    <>
      <ul className={listClassName[layout]}>
        {ads.map((ad) => (
          <li key={ad.id}>
            <Card
              ad={ad}
              imageSizes={imageSizes[layout]}
              // Rows sit under the "Active Listings" heading; the company grid has none above it.
              headingLevel={layout === 'rows' ? 'h3' : 'h2'}
              className={layout === 'grid' ? 'h-full shadow-qb-card' : undefined}
            />
          </li>
        ))}
      </ul>
      {hasNextPage ? (
        <div className="mt-6 flex justify-center">
          <Button
            size="sm"
            onClick={() => void fetchNextPage()}
            disabled={isFetchingNextPage}
            aria-busy={isFetchingNextPage || undefined}
            className="h-[37px] px-[13px] text-qb-body"
          >
            {isFetchingNextPage ? <Loader2Icon className="animate-spin" aria-hidden /> : null}
            {t('users.profile.load_more')}
          </Button>
        </div>
      ) : null}
    </>
  );
}
