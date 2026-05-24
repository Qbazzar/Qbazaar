'use client';

/**
 * RecentlyViewedStrip — horizontally scrollable rail of the last 10 ads the
 * user looked at. Hidden entirely when the list is empty so first-time
 * visitors don't see a sad placeholder.
 *
 * Rendered above "Latest ads" on the home page and (optionally) on `/ads`.
 * Each tile is a slimmed-down AdCard (image + title + price) — the full
 * AdCard is reserved for grids where the meta row is useful.
 */
import Link from 'next/link';

import { BlurHashImage } from '@/components/upload/BlurHashImage';
import { PriceTag } from '@/components/ads/PriceTag';
import { useRecentlyViewedQuery } from '@/lib/queries/recently-viewed';
import { useAuthStore } from '@/store/auth';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { RecentlyViewedAdSummary } from '@/lib/api/types';

interface Props {
  /** Hide the heading row (e.g. when embedded inside an already-titled card). */
  hideHeading?: boolean;
  className?: string;
}

const PER_PAGE = 10;

export function RecentlyViewedStrip({ hideHeading = false, className }: Props) {
  // Recently-viewed is auth-only at the contract level — skip the request
  // entirely for anonymous visitors so we don't trip the 401-refresh dance.
  const isAuthenticated = useAuthStore((s) => Boolean(s.user && s.accessToken));
  const { data, isLoading } = useRecentlyViewedQuery(
    { per_page: PER_PAGE },
    { enabled: isAuthenticated },
  );

  if (!isAuthenticated) return null;
  if (isLoading) {
    return (
      <div className={cn('space-y-3', className)} aria-hidden="true">
        {!hideHeading ? (
          <div className="bg-cream-200 h-4 w-40 animate-pulse rounded" />
        ) : null}
        <div className="flex gap-3 overflow-hidden">
          {Array.from({ length: 4 }).map((_, index) => (
            <div
              key={index}
              className="bg-cream-200 h-40 w-40 shrink-0 animate-pulse rounded-xl"
            />
          ))}
        </div>
      </div>
    );
  }

  const ads = data?.data ?? [];
  if (ads.length === 0) return null;

  return (
    <section className={cn('space-y-3', className)} aria-labelledby="recently-viewed-heading">
      {!hideHeading ? (
        <header className="flex items-end justify-between gap-3">
          <div>
            <p className="text-ink-500 text-[11px] font-bold uppercase tracking-[0.18em]">
              {t('recently_viewed.title', 'آخر ما شاهدت')}
            </p>
            <h2
              id="recently-viewed-heading"
              className="font-display text-ink-900 text-2xl md:text-3xl"
            >
              {t('recently_viewed.strip_heading', 'تابع من حيث وقفت')}
            </h2>
          </div>
          <Link
            href="/account/recently-viewed"
            className="text-coral text-sm font-medium hover:underline"
          >
            {t('common.view_all', 'عرض الكل')}
          </Link>
        </header>
      ) : null}

      <ul className="-mx-1 flex gap-3 overflow-x-auto px-1 pb-2">
        {ads.map((ad) => (
          <li key={ad.id} className="w-44 shrink-0 sm:w-48">
            <RecentlyViewedTile ad={ad} />
          </li>
        ))}
      </ul>
    </section>
  );
}

function RecentlyViewedTile({ ad }: { ad: RecentlyViewedAdSummary }) {
  const image = ad.primary_image;
  return (
    <Link
      href={`/ads/${ad.id}`}
      className="group block overflow-hidden rounded-xl border border-ink-200 bg-card transition-shadow hover:shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-coral focus-visible:ring-offset-2"
    >
      <div className="relative overflow-hidden">
        {image ? (
          <BlurHashImage
            src={image.sizes.thumbnail || image.sizes.medium || image.url}
            alt={ad.title}
            blurhash={image.blurhash}
            aspect="1 / 1"
            className="w-full"
            sizes="200px"
          />
        ) : (
          <div
            className="bg-cream-200 text-ink-500 flex w-full items-center justify-center text-xs"
            style={{ aspectRatio: '1 / 1' }}
          >
            {t('media.no_image', 'بدون صورة')}
          </div>
        )}
      </div>
      <div className="space-y-1 p-2.5">
        <h3 className="text-ink-900 line-clamp-2 text-xs font-medium leading-snug">
          {ad.title}
        </h3>
        <PriceTag price={ad.price} priceType={ad.price_type} size="sm" />
      </div>
    </Link>
  );
}
