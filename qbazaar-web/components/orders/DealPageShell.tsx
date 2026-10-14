import type { ReactNode } from 'react';

import { Breadcrumb } from '@/components/design-system/Breadcrumb';
import type { DealAd } from '@/lib/api/commerce-types';
import { localized } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { isolate } from '@/lib/orders/text';
import { cn } from '@/lib/utils';

import { AdDealSummary } from './AdDealSummary';

/**
 * Page frame of Buy Now and Make an Offer (buy-now.html, offer.html): a 1100 px column with the
 * site gutter, the trail down to the ad, then the form card and the item card side by side in a
 * wrapping row. On phones Buy Now moves the item card above the form (buynow.js); the offer page
 * keeps it under the form. The form stays first in the reading order everywhere.
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
      <div className="mx-auto w-full max-w-[1100px] px-qb-gutter py-[clamp(20px,4vw,40px)]">
        <Breadcrumb
          items={[...crumbs, { label: current }]}
          className="mb-4 qb-tablet:mb-[22px] [&_a]:inline-block [&_a]:max-w-[22ch] [&_a]:truncate [&_a]:align-bottom"
        />
        <div className="flex flex-wrap items-start gap-6">
          {children}
          <aside className={cn('min-w-[min(260px,100%)] flex-[1_1_300px] self-start', summaryAboveOnPhone && 'max-qb-tablet:order-first')}>
            <AdDealSummary ad={ad} />
          </aside>
        </div>
      </div>
    </main>
  );
}
