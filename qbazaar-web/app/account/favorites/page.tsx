'use client';

/**
 * Favorites index — the wishlist of 376:8322 (rows), stacked into the
 * category grid cards on tablets and phones, which have no frame of their
 * own. Auth gated by `account/layout`. The heart on the photo only marks the
 * ad as saved; the trash button is the one control that removes it.
 */
import { useState } from 'react';
import Link from 'next/link';
import { Heart, Trash2 } from 'lucide-react';
import { toast } from 'sonner';

import { buttonVariants } from '@/components/design-system/Button';
import { EmptyState } from '@/components/design-system/EmptyState';
import { Icon } from '@/components/design-system/Icon';
import { AccountPage } from '@/components/account/AccountPage';
import { PanelState } from '@/components/account/PanelState';
import { Pager } from '@/components/account/Pager';
import { SavedAdRow, savedRowButtonClass } from '@/components/account/SavedAdRow';
import { useSlugLabels } from '@/components/account/useSlugLabels';
import { useFavoritesQuery, useToggleFavoriteMutation } from '@/lib/queries/favorites';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import { ApiClientError } from '@/lib/api/auth';

const PER_PAGE = 24;

export default function FavoritesPage() {
  const [page, setPage] = useState(1);
  const { data, isLoading, isError, error } = useFavoritesQuery({
    page,
    per_page: PER_PAGE,
  });
  const toggle = useToggleFavoriteMutation();
  const labels = useSlugLabels();

  const remove = (adId: string) => {
    toggle.mutate(adId, {
      onError: (err) => {
        const code = err instanceof ApiClientError ? err.code : '';
        toast.error(translateMaybeKey(`favorites.errors.${code.toLowerCase()}`) || t('common.error'));
      },
    });
  };

  const lastPage = data?.meta.last_page ?? 1;

  return (
    <AccountPage title={t('favorites.wishlist_title')} titleClassName="qb-desktop:text-[44px]">
      {isLoading ? (
        <PanelState loading />
      ) : isError ? (
        <PanelState
          loading={false}
          message={error instanceof ApiClientError ? error.message : t('common.error', 'حدث خطأ، حاول مرة أخرى')}
        />
      ) : !data || data.data.length === 0 ? (
        <EmptyState
          icon={<Icon icon={Heart} size="lg" />}
          title={t('favorites.empty.title', 'لم تحفظ أي إعلان بعد')}
          description={t('favorites.empty.description')}
          action={
            <Link href="/ads" className={buttonVariants({ size: 'sm' })}>
              {t('favorites.empty.cta', 'تصفّح الإعلانات')}
            </Link>
          }
          className="rounded-qb-2xl border border-qb-line bg-qb-surface py-20 shadow-qb-card"
        />
      ) : (
        <>
          <ul className="flex flex-col gap-4 qb-tablet:gap-6">
            {data.data.map((ad) => (
              <li key={ad.id}>
                <SavedAdRow
                  ad={ad}
                  labels={labels}
                  photoBadge={
                    <span
                      aria-hidden="true"
                      className="flex size-[30px] items-center justify-center rounded-full bg-qb-surface text-qb-danger shadow-qb-soft"
                    >
                      <Heart className="size-4 fill-current" />
                    </span>
                  }
                  action={
                    <button
                      type="button"
                      onClick={() => remove(ad.id)}
                      disabled={toggle.isPending && toggle.variables === ad.id}
                      aria-label={t('favorites.remove_label', { title: ad.title })}
                      className={savedRowButtonClass}
                    >
                      <Trash2 aria-hidden="true" />
                    </button>
                  }
                />
              </li>
            ))}
          </ul>
          <Pager page={page} lastPage={lastPage} onChange={setPage} />
        </>
      )}
    </AccountPage>
  );
}
