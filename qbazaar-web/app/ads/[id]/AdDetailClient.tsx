'use client';

/**
 * Ad detail client island. It shows the copy the server already fetched at
 * once, refetches with the viewer's session and records the view for
 * "recently viewed".
 */
import { useEffect } from 'react';
import Link from 'next/link';

import { AdDetailView } from '@/components/ads/AdDetailView';
import { buttonVariants } from '@/components/design-system/Button';
import { ErrorView } from '@/components/status/ErrorView';
import { NotFoundView } from '@/components/status/NotFoundView';
import { useAdQuery } from '@/lib/queries/ads';
import { useTrackAdViewMutation } from '@/lib/queries/recently-viewed';
import { getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import type { Ad } from '@/lib/api/types';

interface AdDetailClientProps {
  id: string;
  initialAd?: Ad;
}

export function AdDetailClient({ id, initialAd }: AdDetailClientProps) {
  const locale = getLocale();
  const { data, isPending, error, refetch, isFetching } = useAdQuery(id, initialAd);
  const trackView = useTrackAdViewMutation();

  useEffect(() => {
    if (!id) return;
    trackView.mutate(id);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  if (data) return <AdDetailView ad={data} locale={locale} />;
  if (isPending) return <AdDetailSkeleton />;

  if (error?.status === 404) {
    return (
      <NotFoundView
        heading={t('ads.errors.ad_not_found')}
        description={t('ads.errors.ad_not_found_body')}
        actions={
          <>
            <Link href="/ads" className={buttonVariants({ size: 'sm' })}>
              {t('ads.empty.go_browse')}
            </Link>
            <Link href="/" className={buttonVariants({ variant: 'outline', size: 'sm' })}>
              {t('errors.back_home')}
            </Link>
          </>
        }
      />
    );
  }

  return <ErrorView onRetry={() => void refetch()} retrying={isFetching} />;
}

/** Same frame as the page, so nothing jumps when the ad arrives. */
function AdDetailSkeleton() {
  const block = 'animate-pulse rounded-qb-xl bg-qb-fill motion-reduce:animate-none';
  return (
    <main aria-busy="true" className="bg-qb-page pb-16">
      <span className="sr-only">{t('common.loading')}</span>
      <div aria-hidden="true" className="mx-auto max-w-[1440px] px-qb-gutter pt-[clamp(20px,4vw,40px)]">
        <div className="mb-[18px] hidden h-[30px] w-72 animate-pulse rounded-qb-sm bg-qb-fill motion-reduce:animate-none qb-tablet:block" />
        <div className="flex flex-col gap-6 qb-tablet:flex-row qb-tablet:items-start">
          <div className="flex min-w-0 flex-col gap-6 qb-tablet:flex-1 qb-desktop:flex-[2_1_560px]">
            <div className={`h-[min(56vw,420px)] ${block}`} />
            <div className={`h-[277px] ${block}`} />
          </div>
          <div className="flex min-w-0 flex-col gap-5 qb-tablet:w-[260px] qb-tablet:shrink-0 qb-desktop:w-auto qb-desktop:flex-[1_1_300px]">
            <div className={`h-[467px] ${block}`} />
            <div className={`h-[196px] ${block}`} />
          </div>
        </div>
      </div>
    </main>
  );
}
