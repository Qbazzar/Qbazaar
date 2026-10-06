'use client';

import Link from 'next/link';
import { BadgeCheck, Clock } from 'lucide-react';

import { Button, buttonVariants } from '@/components/design-system/Button';
import { Icon } from '@/components/design-system/Icon';
import type { Ad } from '@/lib/api/types';
import { t } from '@/lib/i18n/messages';

import { VIEW_HEADING_ID } from './view-heading';

/**
 * After publishing: every ad waits for an admin ("Under review"). No frame
 * designs it; it uses the icon tile and panel of the empty states.
 */
export function ReviewState({ ad, onPostAnother }: { ad: Ad | null; onPostAnother: () => void }) {
  const live = ad?.status === 'active';
  return (
    <section className="flex flex-col items-center rounded-qb-2xl border border-qb-line bg-qb-surface px-5 py-12 text-center font-qb qb-tablet:px-10 qb-tablet:py-16">
      <div
        aria-hidden="true"
        className="mb-6 flex size-[92px] items-center justify-center rounded-qb-2xl border border-qb-line bg-qb-surface text-qb-brand shadow-qb-brand"
      >
        <Icon icon={live ? BadgeCheck : Clock} size="lg" className="size-9" />
      </div>
      <h1 id={VIEW_HEADING_ID} tabIndex={-1} className="text-qb-h4 font-semibold tracking-normal text-qb-ink outline-none qb-tablet:text-qb-h3">
        {t(live ? 'post_ad.review.live_title' : 'post_ad.review.title')}
      </h1>
      <p className="mt-2.5 max-w-[560px] text-qb-body text-qb-ink-secondary qb-tablet:text-qb-body-lg">
        {t(live ? 'post_ad.review.live_body' : 'post_ad.review.body')}
      </p>
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
