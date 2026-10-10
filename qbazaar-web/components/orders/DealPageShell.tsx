import type { ReactNode } from 'react';

import { Breadcrumb } from '@/components/design-system/Breadcrumb';
import type { DealAd } from '@/lib/api/commerce-types';
import { localized } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { isolate } from '@/lib/orders/text';
import { cn } from '@/lib/utils';

import { pageFrame } from './AccountPageFrame';
import { AdDealSummary } from './AdDealSummary';

/**
 * Page frame of Buy Now and Make an Offer: the trail down to the ad, the
 * form, and the item card beside it. On phones Buy Now moves the card above
 * the form (buynow.js, 709:33450) while the offer page keeps it under the
 * form (709:33285); the form stays first in the reading order everywhere.
 */
export function DealPageShell({
  ad,
  current,
  summaryAboveOnPhone = false,
  children,
}: {
  ad: DealAd;
  current: string;
  summaryAboveOnPhone?: boolean;
  children: ReactNode;
}) {
  const crumbs = [{ label: t('home.breadcrumb'), href: '/' }];
  if (ad.category?.slug) {
    crumbs.push({ label: localized(ad.category.name) || ad.category.slug, href: `/c/${encodeURIComponent(ad.category.slug)}` });
  }
  crumbs.push({ label: isolate(ad.title), href: `/ads/${encodeURIComponent(ad.id)}` });

  return (
    <main className="bg-qb-page font-qb text-qb-ink">
      <div className={cn(pageFrame, 'pt-12 pb-16 qb-tablet:pt-[35px] qb-desktop:pt-16 qb-desktop:pb-24')}>
        <Breadcrumb
          items={[...crumbs, { label: current }]}
          className="hidden text-qb-caption qb-tablet:block qb-desktop:text-qb-h5 [&_a]:inline-block [&_a]:max-w-[22ch] [&_a]:truncate [&_a]:align-bottom"
        />
        <div className="flex flex-col gap-6 qb-tablet:mt-5 qb-tablet:flex-row qb-tablet:items-start qb-desktop:mt-[62px] qb-desktop:gap-[33px]">
          <div className="min-w-0 flex-1">{children}</div>
          <aside className={cn('qb-tablet:w-[258px] qb-tablet:shrink-0 qb-desktop:w-[421px]', summaryAboveOnPhone && 'order-first qb-tablet:order-none')}>
            <AdDealSummary ad={ad} />
          </aside>
        </div>
      </div>
    </main>
  );
}
