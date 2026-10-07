import Image from 'next/image';
import Link from 'next/link';
import { BadgeCheck, MapPin } from 'lucide-react';

import { Avatar } from '@/components/design-system/Avatar';
import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import type { DealAd } from '@/lib/api/commerce-types';
import { localized } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { formatListPrice } from '@/lib/orders/money';
import { cn } from '@/lib/utils';

import { panelClass } from './CheckoutPanel';

/**
 * The item card beside the Buy Now and Make an Offer forms (659:58417,
 * 709:32645): photo, title, asking price, place and seller. On phones it
 * turns into a compact row above the form (709:33450).
 */
export function AdDealSummary({ ad }: { ad: DealAd }) {
  const photo = ad.images?.[0];
  const sellerName = ad.user?.business_name || ad.user?.full_name;
  const verified = Boolean(ad.user?.verification_badges?.phone_verified ?? ad.user?.phone_verified);
  const place = localized(ad.location?.name);

  return (
    <article aria-labelledby="deal-ad-title" className={cn(panelClass, 'overflow-hidden')}>
      <div className="flex gap-3 p-4 qb-tablet:flex-col qb-tablet:gap-0 qb-tablet:p-0">
        <div className="relative size-28 shrink-0 overflow-hidden rounded-qb-lg bg-qb-fill qb-tablet:h-[180px] qb-tablet:w-full qb-tablet:rounded-none qb-desktop:h-[223px]">
          {photo ? (
            <Image
              src={photo.sizes.medium || photo.sizes.thumbnail}
              alt=""
              fill
              sizes="(min-width: 1001px) 421px, (min-width: 601px) 258px, 112px"
              className="object-cover"
              preload
            />
          ) : null}
        </div>
        <div className="min-w-0 flex-1 qb-tablet:px-5 qb-tablet:pt-4">
          <h2
            id="deal-ad-title"
            className="line-clamp-2 font-qb text-qb-caption leading-snug font-semibold tracking-normal text-qb-ink-title qb-tablet:text-qb-body qb-tablet:font-medium qb-desktop:text-qb-h5"
          >
            <Link href={`/ads/${encodeURIComponent(ad.id)}`} dir="auto" className={cn('rounded-qb-xs hover:underline', focusRing)}>
              {ad.title}
            </Link>
          </h2>
          {ad.price != null ? (
            <p className="mt-1.5 text-qb-micro font-semibold text-qb-brand qb-tablet:mt-3 qb-tablet:text-qb-body-sm qb-desktop:text-qb-body qb-desktop:font-medium qb-desktop:text-qb-ink-secondary">
              {t('orders.deal.asking_price')} {formatListPrice(ad.price, ad.currency)}
            </p>
          ) : null}
          {place ? (
            <p className="mt-2 flex items-center gap-1.5 text-qb-micro text-qb-ink-subtle qb-desktop:mt-4 qb-desktop:text-qb-caption">
              <Icon icon={MapPin} size="sm" />
              <span className="truncate">{place}</span>
            </p>
          ) : null}
        </div>
      </div>
      {sellerName ? (
        <div className="mx-4 flex items-center gap-2.5 border-t border-qb-line py-3 qb-tablet:mx-5 qb-tablet:mt-4 qb-desktop:py-4">
          {/* The name follows in text, so the photo is not read out as well. */}
          <span aria-hidden="true" className="flex shrink-0">
            <Avatar
              name={sellerName}
              src={ad.user?.avatar_thumb_url ?? ad.user?.avatar_url}
              className="size-10 border border-qb-line bg-qb-hover text-qb-caption text-qb-ink qb-desktop:size-[50px] qb-desktop:text-qb-body"
            />
          </span>
          <p className="flex min-w-0 flex-1 items-center gap-1 text-qb-caption font-semibold text-qb-ink qb-desktop:text-qb-body">
            <span dir="auto" className="truncate">
              {sellerName}
            </span>
            {verified ? <Icon icon={BadgeCheck} size="sm" className="text-qb-info" /> : null}
          </p>
          {verified ? (
            <span className="shrink-0 rounded-qb-xs border border-qb-success bg-qb-success-soft px-2 py-0.5 font-qb-label text-qb-tiny font-medium text-qb-success">
              {t('orders.deal.verified')}
            </span>
          ) : null}
        </div>
      ) : null}
    </article>
  );
}
