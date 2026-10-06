import type { ReactNode } from 'react';
import Link from 'next/link';
import { MapPin } from 'lucide-react';

import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { formatRelativeTime } from '@/components/messaging/relative-time';
import { cn } from '@/lib/utils';
import type { AdSummary } from '@/lib/api/types';

import { AdPhoto } from './AdPhoto';
import { formatAdPrice, labelFromSlug } from './format';

export interface SavedAdRowProps {
  ad: AdSummary;
  /** Control laid over the photo's top end corner (the favourite heart). */
  photoAction?: ReactNode;
  /** Control at the bottom end of the row (remove). */
  action?: ReactNode;
  /** When the ad was saved or viewed, shown in the meta line. */
  at?: string | null;
}

/**
 * Wishlist row of 376:8322: photo, title with the price on the end side, the
 * category and "Doha • 30 min ago". Stacks on phones (654:50760), with a
 * narrower photo on tablets (655:54431).
 */
export function SavedAdRow({ ad, photoAction, action, at }: SavedAdRowProps) {
  const location = labelFromSlug(ad.location_slug);
  const when = formatRelativeTime(at ?? ad.published_at ?? ad.created_at);
  const meta = [location, when].filter(Boolean).join(' • ');

  return (
    <article
      className={cn(
        'relative flex flex-col overflow-hidden rounded-qb-2xl border border-qb-line bg-qb-surface font-qb shadow-qb-card qb-tablet:flex-row',
        'has-[a:focus-visible]:outline-2 has-[a:focus-visible]:outline-offset-2 has-[a:focus-visible]:outline-qb-brand-active',
      )}
    >
      <AdPhoto
        image={ad.primary_image}
        sizes="(min-width: 1001px) 317px, (min-width: 601px) 220px, 100vw"
        className="h-[180px] w-full rounded-qb-2xl qb-tablet:h-auto qb-tablet:min-h-[205px] qb-tablet:w-[220px] qb-desktop:min-h-[222px] qb-desktop:w-[317px]"
      >
        {photoAction ? <div className="absolute end-4 top-4 z-10">{photoAction}</div> : null}
      </AdPhoto>

      <div className="flex min-w-0 flex-1 flex-col p-4 qb-tablet:px-6 qb-tablet:py-7 qb-desktop:px-8 qb-desktop:pt-[38px] qb-desktop:pb-7">
        <div className="flex items-start justify-between gap-4">
          <h3 className="min-w-0 text-qb-body font-medium tracking-normal text-qb-ink-body qb-tablet:text-qb-h5">
            <Link href={`/ads/${ad.id}`} className="outline-none after:absolute after:inset-0 hover:text-qb-brand">
              <bdi>{ad.title}</bdi>
            </Link>
          </h3>
          <span className="shrink-0 text-qb-caption font-semibold text-qb-ink qb-tablet:text-qb-h4">
            {formatAdPrice(ad.price, ad.price_type)}
          </span>
        </div>
        <p className="mt-2 text-qb-label text-qb-ink-subtle capitalize qb-tablet:mt-4 qb-tablet:text-qb-body">
          {labelFromSlug(ad.category_slug)}
        </p>
        <div className="mt-auto flex items-end justify-between gap-4 pt-4">
          <p className="flex min-w-0 items-center gap-1.5 text-qb-micro text-qb-ink-subtle capitalize qb-tablet:text-qb-caption">
            {meta ? (
              <>
                <Icon icon={MapPin} size="sm" />
                <span className="truncate">{meta}</span>
              </>
            ) : null}
          </p>
          {action ? <div className="relative z-10 shrink-0">{action}</div> : null}
        </div>
      </div>
    </article>
  );
}

/** Grey square icon button of the wishlist rows (the trash at the bottom end). */
export const savedRowButtonClass = cn(
  'inline-flex size-[34px] items-center justify-center rounded-qb-sm bg-qb-fill text-qb-danger hover:bg-qb-danger-soft disabled:opacity-50 [&_svg]:size-[18px]',
  focusRing,
);
