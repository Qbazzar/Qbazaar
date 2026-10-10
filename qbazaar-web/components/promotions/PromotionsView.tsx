'use client';

import { useState } from 'react';
import Image from 'next/image';
import Link from 'next/link';
import { Megaphone } from 'lucide-react';

import { Pager } from '@/components/account/Pager';
import { buttonVariants } from '@/components/design-system/Button';
import { Icon } from '@/components/design-system/Icon';
import { AccountPageFrame } from '@/components/orders/AccountPageFrame';
import { PageState } from '@/components/orders/PageState';
import { StatusPill } from '@/components/orders/StatusPill';
import { LoadMore, TableCard, Th, tableClasses as tc } from '@/components/orders/TableCard';
import type { AdPromotion, PromotionType } from '@/lib/api/commerce-types';
import type { AdSummary } from '@/lib/api/types';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';
import { formatDate, isoDate } from '@/lib/orders/dates';
import { formatMoney } from '@/lib/orders/money';
import { PROMOTION_TONE } from '@/lib/orders/status';
import { isolate } from '@/lib/orders/text';
import { useMyAdsQuery } from '@/lib/queries/ads';
import { useMyPromotionsQuery } from '@/lib/queries/promotions';
import { cn } from '@/lib/utils';

/** `/account/promotions`: pick a live ad to promote, and follow the promotions bought. */
export function PromotionsView() {
  return (
    <AccountPageFrame
      breadcrumb={[{ label: t('orders.promotion.title') }]}
      title={t('orders.promotion.title')}
      description={t('orders.promotion.subtitle')}
    >
      <PromotableAds />
      <PromotionsTable />
    </AccountPageFrame>
  );
}

type SummaryWithPromotion = AdSummary & { promotion?: PromotionType | null };

/** The seller's live ads, a page at a time as `GET /account/ads` serves them. */
function PromotableAds() {
  const [page, setPage] = useState(1);
  const query = useMyAdsQuery({ status: 'active', page });
  const ads = (query.data?.data ?? []) as SummaryWithPromotion[];

  // The same card heading as "Your promotions" below it (502:22401).
  return (
    <TableCard title={t('orders.promotion.pick_title')} titleId="promote-pick">
      <div className="px-5 pb-5 qb-tablet:px-[18px] qb-desktop:px-8 qb-desktop:pb-8">
        <p className="-mt-1 mb-4 text-qb-caption text-qb-ink-subtle">{t('orders.promotion.pick_body')}</p>
        {query.isPending ? (
          <PageState kind="loading" />
        ) : query.isError ? (
          <PageState kind="error" onRetry={() => query.refetch()} />
        ) : ads.length === 0 ? (
          <p className="text-qb-body text-qb-ink-secondary">{t('orders.promotion.no_live_ads')}</p>
        ) : (
          <>
            <ul className="flex flex-col" aria-busy={query.isPlaceholderData}>
              {ads.map((ad) => (
                <PromotableAd key={ad.id} ad={ad} />
              ))}
            </ul>
            <Pager page={page} lastPage={query.data.meta.last_page} onChange={setPage} />
          </>
        )}
      </div>
    </TableCard>
  );
}

function PromotableAd({ ad }: { ad: SummaryWithPromotion }) {
  const thumb = ad.primary_image?.sizes.thumbnail;
  return (
    <li className="flex items-center gap-3 border-b border-qb-line py-3 first:pt-0 last:border-b-0 last:pb-0">
      <span className="relative size-[50px] shrink-0 overflow-hidden rounded-qb-sm bg-qb-fill">
        {thumb ? <Image src={thumb} alt="" fill sizes="50px" className="object-cover" /> : null}
      </span>
      <div className="min-w-0 flex-1">
        <p dir="auto" className="truncate text-start text-qb-body font-semibold text-qb-ink-title">
          {ad.title}
        </p>
        {ad.promotion ? <p className="mt-0.5 text-qb-micro text-qb-success">{t(`orders.promotion.types.${ad.promotion}`)}</p> : null}
      </div>
      <Link
        href={`/account/ads/${encodeURIComponent(ad.id)}/promote`}
        aria-label={t('orders.promotion.promote_label', { title: isolate(ad.title) })}
        className={cn(buttonVariants({ size: 'sm', variant: 'soft' }), 'rounded-qb-sm')}
      >
        <Megaphone aria-hidden="true" />
        {t('orders.promotion.promote')}
      </Link>
    </li>
  );
}

function PromotionsTable() {
  const query = useMyPromotionsQuery();
  const rows = query.data?.pages.flatMap((page) => page.data) ?? [];

  return (
    <TableCard title={t('orders.promotion.mine_title')} titleId="promotions-mine">
      {query.isPending ? (
        <PageState kind="loading" />
      ) : query.isError ? (
        <PageState kind="error" onRetry={() => query.refetch()} />
      ) : rows.length === 0 ? (
        <p className="flex items-center justify-center gap-2 border-t border-qb-line px-5 py-8 text-center text-qb-body text-qb-ink-secondary">
          <Icon icon={Megaphone} className="text-qb-ink-subtle" />
          {t('orders.promotion.empty')}
        </p>
      ) : (
        <>
          <table className={tc.table} aria-labelledby="promotions-mine">
            <thead>
              <tr className={tc.headRow}>
                <Th>{t('orders.promotion.columns.ad')}</Th>
                <Th className={tc.wide}>{t('orders.promotion.columns.period')}</Th>
                <Th className={tc.wide}>{t('orders.promotion.columns.status')}</Th>
                <Th className="text-end">{t('orders.promotion.columns.price')}</Th>
              </tr>
            </thead>
            <tbody>
              {rows.map((promotion) => (
                <PromotionRow key={promotion.id} promotion={promotion} />
              ))}
            </tbody>
          </table>
          {query.hasNextPage ? <LoadMore onClick={() => void query.fetchNextPage()} loading={query.isFetchingNextPage} /> : null}
        </>
      )}
    </TableCard>
  );
}

function PromotionRow({ promotion }: { promotion: AdPromotion }) {
  const tone = PROMOTION_TONE[promotion.status];
  const label = t(`orders.status.promotion.${promotion.status}`);
  const period =
    promotion.starts_at && promotion.ends_at ? (
      <span className="whitespace-nowrap">
        <time dateTime={isoDate(promotion.starts_at)}>{formatDate(promotion.starts_at)}</time>
        {' – '}
        <time dateTime={isoDate(promotion.ends_at)}>{formatDate(promotion.ends_at)}</time>
      </span>
    ) : (
      t('orders.promotion.pending_period')
    );

  return (
    <tr className={tc.row}>
      <td className={tc.td}>
        <p dir="auto" className="text-start font-semibold text-qb-ink-title">
          {promotion.ad_title ?? t(`orders.promotion.types.${promotion.type}`)}
        </p>
        <p className="mt-0.5 text-qb-micro font-normal text-qb-ink-subtle qb-desktop:text-qb-caption">
          {t(`orders.promotion.types.${promotion.type}`)} · {tPlural('orders.promotion.duration', promotion.duration_days)} ·{' '}
          {t(`orders.promotion.paid_by.${promotion.payment_method}`)}
          <span className="qb-tablet:hidden"> · {period}</span>
        </p>
        {promotion.rejection_reason ? (
          <p className="mt-1 text-qb-micro font-normal text-qb-danger qb-desktop:text-qb-caption">
            {t('orders.promotion.rejected_reason', { reason: isolate(promotion.rejection_reason) })}
          </p>
        ) : null}
      </td>
      <td className={cn(tc.td, tc.wide)}>{period}</td>
      <td className={cn(tc.td, tc.wide)}>
        <StatusPill tone={tone}>{label}</StatusPill>
      </td>
      <td className={cn(tc.td, tc.amount)}>
        {formatMoney(promotion.price, promotion.currency)}
        <div className="mt-1 qb-tablet:hidden">
          <StatusPill tone={tone} compact>
            {label}
          </StatusPill>
        </div>
      </td>
    </tr>
  );
}
