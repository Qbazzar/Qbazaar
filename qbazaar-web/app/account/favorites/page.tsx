'use client';

/**
 * FE-7.x — Favorites index.
 *
 * Auth-gated by the wrapping `app/account/layout.tsx`. Lists every ad the
 * user has favorited as a paginated `AdGrid`. The query hydrates the
 * favorites store on success so the heart icons across the rest of the app
 * stay in sync after a refresh.
 */
import { useState } from 'react';
import { ChevronLeft, ChevronRight, Loader2Icon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { AdGrid } from '@/components/ads/AdGrid';
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
    <div className="space-y-6">
      <header className="space-y-2">
        <p className="text-coral text-xs font-bold uppercase tracking-[0.18em]">
          {t('account.nav.favorites', 'الإعلانات المحفوظة')}
        </p>
        <div className="flex flex-wrap items-end justify-between gap-3">
          <h1 className="font-display text-ink-900 text-3xl md:text-4xl">
            {t('favorites.title', 'الإعلانات المحفوظة')}
          </h1>
          {data ? (
            <span className="bg-coral/10 text-coral inline-flex items-center rounded-full px-3 py-1 text-xs font-bold">
              {t('favorites.count', { count: String(total) }, `${total} إعلان`)}
            </span>
          ) : null}
        </div>
      </header>

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
          <AdGrid ads={data.data} />
          {lastPage > 1 ? (
            <nav className="mt-8 flex items-center justify-between">
              <Button
                type="button"
                variant="outline"
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                disabled={page <= 1}
              >
                <ChevronRight className="size-4" />
                {t('ads.list.prev', 'السابق')}
              </Button>
              <span className="text-ink-500 text-sm">
                {t(
                  'ads.list.page_of',
                  { current: String(page), total: String(lastPage) },
                  `${page} / ${lastPage}`,
                )}
              </span>
              <Button
                type="button"
                variant="outline"
                onClick={() => setPage((p) => Math.min(lastPage, p + 1))}
                disabled={page >= lastPage}
              >
                {t('ads.list.next', 'التالي')}
                <ChevronLeft className="size-4" />
              </Button>
            </nav>
          ) : null}
        </>
      )}
    </div>
  );
}
