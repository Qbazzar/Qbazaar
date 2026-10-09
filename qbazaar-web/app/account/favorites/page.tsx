'use client';

/**
 * Wishlist — `/account/favorites` (wishlist.html): the "Wishlist" title with
 * "Clear all" on the end side, then the saved ads as listing cards, as many
 * 260 px columns as fit. The filled heart on each card unsaves the ad. Auth
 * gated by `account/layout`, which also owns the `<main>`.
 */
import { useState } from 'react';
import Link from 'next/link';
import { Heart } from 'lucide-react';
import { toast } from 'sonner';

import { ListingCard } from '@/components/ads/ListingCard';
import { PanelState } from '@/components/account/PanelState';
import { Pager } from '@/components/account/Pager';
import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { useQatarLocationsQuery } from '@/lib/queries/locations';
import { useClearFavoritesMutation, useFavoritesQuery } from '@/lib/queries/favorites';
import { t } from '@/lib/i18n/messages';
import { ApiClientError } from '@/lib/api/auth';
import { cn } from '@/lib/utils';

const PER_PAGE = 24;

export default function FavoritesPage() {
  const [page, setPage] = useState(1);
  const { data, isLoading, isError, error } = useFavoritesQuery({ page, per_page: PER_PAGE });
  const clearAll = useClearFavoritesMutation();
  // Cards name their place from the locations tree in the store.
  useQatarLocationsQuery();

  const ads = data?.data ?? [];
  const lastPage = data?.meta.last_page ?? 1;

  const clear = () => {
    if (clearAll.isPending) return;
    clearAll.mutate(undefined, {
      onSuccess: () => setPage(1),
      onError: () => toast.error(t('common.error')),
    });
  };

  return (
    <div className="mx-auto w-full max-w-[1440px] px-qb-gutter py-[clamp(20px,4vw,40px)] font-qb text-qb-ink">
      <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h1 className="text-[clamp(28px,4vw,40px)] font-semibold tracking-normal text-qb-ink">{t('favorites.wishlist_title')}</h1>
        {ads.length ? (
          <button
            type="button"
            onClick={clear}
            aria-busy={clearAll.isPending || undefined}
            className={cn('cursor-pointer rounded-qb-xs text-qb-caption text-qb-ink-subtle transition-colors hover:text-qb-brand', focusRing)}
          >
            {t('favorites.clear_all')}
          </button>
        ) : null}
      </div>

      {isLoading ? (
        <PanelState loading />
      ) : isError ? (
        <PanelState loading={false} message={error instanceof ApiClientError ? error.message : t('common.error')} />
      ) : ads.length === 0 ? (
        <WishlistEmpty />
      ) : (
        <>
          <ul aria-busy={clearAll.isPending || undefined} className="[display:grid] grid-cols-[repeat(auto-fill,minmax(260px,1fr))] gap-5">
            {ads.map((ad) => (
              <li key={ad.id}>
                <ListingCard ad={ad} variant="saved" favorited headingLevel="h2" />
              </li>
            ))}
          </ul>
          <Pager page={page} lastPage={lastPage} onChange={setPage} />
        </>
      )}
    </div>
  );
}

/** The reference's empty wishlist: a white r20 panel with the peach heart and "Browse Listings". */
function WishlistEmpty() {
  return (
    <section className="flex flex-col items-center gap-4 rounded-[20px] border border-qb-line bg-qb-surface px-6 py-[60px] text-center">
      <span aria-hidden="true" className="flex size-[88px] items-center justify-center rounded-full bg-qb-brand-soft text-qb-brand">
        <Icon icon={Heart} className="size-10" />
      </span>
      <h2 className="text-qb-h4 font-semibold tracking-normal text-qb-ink">{t('favorites.wishlist_empty.title')}</h2>
      <p className="max-w-[380px] text-qb-body-sm text-qb-ink-subtle">{t('favorites.wishlist_empty.description')}</p>
      <Link
        href="/"
        className={cn(
          'mt-2 rounded-qb-lg bg-qb-brand px-8 py-3.5 text-qb-body-sm font-semibold text-qb-on-brand transition-colors hover:bg-qb-brand-hover',
          focusRing,
        )}
      >
        {t('favorites.wishlist_empty.cta')}
      </Link>
    </section>
  );
}
