'use client';

/**
 * Favorites index — QBFront port. Renders saved ads as `.cat-listings` grid
 * with the prototype's header + pill counter. Auth gated by `account/layout`.
 */
import { useState } from 'react';
import { Loader2Icon } from 'lucide-react';

import { QbfListingCard } from '@/components/ads/QbfListingCard';
import { FavoritesEmptyState } from '@/components/account/FavoritesEmptyState';
import { useFavoritesQuery } from '@/lib/queries/favorites';
import { t } from '@/lib/i18n/messages';
import { ApiClientError } from '@/lib/api/auth';

const PER_PAGE = 24;

export default function FavoritesPage() {
  const [page, setPage] = useState(1);
  const { data, isLoading, isError, error } = useFavoritesQuery({
    page,
    per_page: PER_PAGE,
  });

  const total = data?.meta.total ?? 0;
  const lastPage = data?.meta.last_page ?? 1;

  return (
    <div className="container" style={{ paddingTop: 24, paddingBottom: 48 }}>
      <div className="saved-head">
        <div>
          <h1 className="cat-page__title">
            {t('favorites.title', 'الإعلانات المحفوظة')}
          </h1>
          {data ? (
            <p className="cat-page__meta">
              <strong>
                {t('favorites.count', { count: String(total) }, `${total} إعلان`)}
              </strong>
            </p>
          ) : null}
        </div>
      </div>

      {isLoading ? (
        <div className="flex justify-center py-12" role="status">
          <Loader2Icon
            className="text-muted-foreground size-6 animate-spin"
            aria-hidden="true"
          />
        </div>
      ) : isError ? (
        <p className="text-destructive py-12 text-center text-sm">
          {error instanceof ApiClientError
            ? error.message
            : t('common.error', 'حدث خطأ، حاول مرة أخرى')}
        </p>
      ) : !data || data.data.length === 0 ? (
        <FavoritesEmptyState />
      ) : (
        <>
          <div className="cat-listings">
            {data.data.map((ad) => (
              <QbfListingCard key={ad.id} ad={ad} />
            ))}
          </div>
          {lastPage > 1 ? (
            <div className="pagination">
              <button
                type="button"
                className="pagination__num"
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                disabled={page <= 1}
                aria-label={t('ads.list.prev', 'السابق')}
              >
                ‹
              </button>
              <span className="pagination__gap">
                {t(
                  'ads.list.page_of',
                  { current: String(page), total: String(lastPage) },
                  `${page} / ${lastPage}`,
                )}
              </span>
              <button
                type="button"
                className="pagination__num"
                onClick={() => setPage((p) => Math.min(lastPage, p + 1))}
                disabled={page >= lastPage}
                aria-label={t('ads.list.next', 'التالي')}
              >
                ›
              </button>
            </div>
          ) : null}
        </>
      )}
    </div>
  );
}
