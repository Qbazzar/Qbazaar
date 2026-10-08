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
import { pageFrame } from '@/components/design-system/page-frame';
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
  const block = 'animate-pulse rounded-qb-2xl bg-qb-fill motion-reduce:animate-none';
  return (
    <main aria-busy="true" className="bg-qb-page pb-16">
      <span className="sr-only">{t('common.loading')}</span>
      <div aria-hidden="true" className={`${pageFrame} qb-tablet:pt-[72px] qb-desktop:pt-[65px]`}>
        <div className="hidden h-7 w-72 animate-pulse rounded-qb-sm bg-qb-fill motion-reduce:animate-none qb-tablet:block" />
        <div className="[display:grid] grid-cols-1 gap-4 qb-tablet:mt-[49px] qb-tablet:grid-cols-[minmax(0,1fr)_263px] qb-tablet:gap-x-[17px] qb-tablet:gap-y-6 qb-desktop:grid-cols-[minmax(0,1fr)_421px] qb-desktop:gap-x-8">
          <div className={`-mx-4 h-80 qb-tablet:col-span-2 qb-tablet:mx-0 qb-tablet:h-[322px] qb-desktop:col-span-1 qb-desktop:h-[502px] ${block}`} />
          <div className="flex flex-col gap-4 qb-tablet:col-start-1 qb-tablet:row-start-2 qb-desktop:gap-6">
            <div className={`h-[176px] qb-desktop:h-[298px] ${block}`} />
            <div className={`h-[280px] ${block}`} />
          </div>
          <div className="flex flex-col gap-4 qb-tablet:col-start-2 qb-tablet:row-start-2 qb-desktop:[grid-row:1/span_2] qb-desktop:gap-6">
            <div className={`h-[374px] qb-desktop:h-[444px] ${block}`} />
            <div className={`h-[200px] ${block}`} />
          </div>
        </div>
      </div>
    </main>
  );
}
