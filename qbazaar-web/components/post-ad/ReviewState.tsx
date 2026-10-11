'use client';

import Link from 'next/link';
import { BadgeCheck, Clock } from 'lucide-react';

import { Button, buttonVariants } from '@/components/design-system/Button';
import { cardVariants } from '@/components/design-system/Card';
import { Icon } from '@/components/design-system/Icon';
import type { Ad } from '@/lib/api/types';
import { t } from '@/lib/i18n/messages';
import { formatMoney } from '@/lib/orders/money';
import { cn } from '@/lib/utils';

import { promotionTitle, usePromotionChoices } from './PromotionChoices';
import { VIEW_HEADING_ID } from './view-heading';

/**
 * After publishing: every ad waits for an admin ("Under review"). No frame
 * designs it, so it takes the look of the empty states (376:7817); it keeps
 * its own markup because the design-system EmptyState has no focusable h1.
 */
export function ReviewState({ ad, onPostAnother }: { ad: Ad | null; onPostAnother: () => void }) {
  const live = ad?.status === 'active';
  return (
    <section className={cn(cardVariants({ large: true, padding: 'none' }), 'flex flex-col items-center px-5 py-12 text-center qb-tablet:px-10 qb-tablet:py-16')}>
      <div
        aria-hidden="true"
        className="mb-6 flex size-[92px] items-center justify-center rounded-qb-2xl border border-qb-line bg-qb-surface text-qb-brand shadow-qb-brand"
      >
        <Icon icon={live ? BadgeCheck : Clock} size="lg" className="size-9" />
      </div>
      <h1
        id={VIEW_HEADING_ID}
        tabIndex={-1}
        className="text-qb-body-lg font-medium tracking-normal text-qb-ink-muted outline-none qb-tablet:text-qb-h4"
      >
        {t(live ? 'post_ad.review.live_title' : 'post_ad.review.title')}
      </h1>
      <p className="mt-2 max-w-[806px] text-qb-body text-qb-ink-disabled qb-tablet:text-qb-h5">
        {t(live ? 'post_ad.review.live_body' : 'post_ad.review.body')}
      </p>
      {ad ? <ChosenPromotions ad={ad} live={live} /> : null}
      <div className="mt-8 flex w-full flex-col justify-center gap-3 qb-tablet:w-auto qb-tablet:flex-row">
        {live && ad ? (
          <Link href={`/ads/${ad.id}`} className={buttonVariants({ size: 'md' })}>
            {t('post_ad.actions.view_ad')}
          </Link>
        ) : (
          <Link href="/account/ads" className={buttonVariants({ size: 'md' })}>
            {t('post_ad.actions.my_ads')}
          </Link>
        )}
        <Button variant="secondary" onClick={onPostAnother}>
          {t('post_ad.actions.post_another')}
        </Button>
      </div>
    </section>
  );
}

/**
 * The promotions ticked while posting. They are bought for a live ad, so a
 * live ad links to the promote page and an ad in review points to Promotions.
 */
function ChosenPromotions({ ad, live }: { ad: Ad; live: boolean }) {
  const { offers, chosen } = usePromotionChoices();
  const picked = offers.filter((offer) => chosen.includes(offer.type));
  if (picked.length === 0) return null;

  return (
    <div className="mt-8 w-full max-w-[560px] rounded-qb-lg border border-qb-line p-5 text-start">
      <h2 className="text-qb-body font-semibold tracking-normal text-qb-ink">{t('post_ad.review.promotions_title')}</h2>
      <p className="mt-1 text-qb-caption text-qb-ink-subtle">
        {t(live ? 'post_ad.review.promotions_live' : 'post_ad.review.promotions_pending')}
      </p>
      <ul className="mt-3 flex flex-col divide-y divide-qb-line">
        {picked.map((offer) => (
          <li key={offer.type} className="flex items-center justify-between gap-4 py-3 text-qb-body-sm text-qb-ink">
            <span>
              {promotionTitle(offer.type)}
              <span className="text-qb-ink-subtle"> · {formatMoney(offer.price, offer.currency)}</span>
            </span>
            {live ? (
              <Link
                href={`/account/ads/${encodeURIComponent(ad.id)}/promote?type=${offer.type}`}
                className={buttonVariants({ variant: 'secondary', size: 'sm' })}
              >
                {t('post_ad.review.promote')}
              </Link>
            ) : null}
          </li>
        ))}
      </ul>
      {live ? null : (
        <Link href="/account/promotions" className="mt-2 inline-block text-qb-caption font-medium text-qb-brand hover:underline">
          {t('post_ad.review.go_promotions')}
        </Link>
      )}
    </div>
  );
}
