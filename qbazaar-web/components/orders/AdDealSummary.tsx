import Image from 'next/image';
import Link from 'next/link';

import { Avatar } from '@/components/design-system/Avatar';
import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { LocationPinSlimIcon, ShieldCheckSafepayIcon } from '@/components/design-system/icons';
import { VerifiedMark } from '@/components/users/SellerBadges';
import type { DealAd } from '@/lib/api/commerce-types';
import { localized } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { formatListPrice } from '@/lib/orders/money';
import { cn } from '@/lib/utils';

/**
 * The item card beside the Buy Now and Make an Offer forms (buy-now.html, offer.html): an r16
 * card with 16 px padding, the 150 px r12 photo, the 16 px semibold title, the asking price,
 * the place, and the seller under a divider with the green outlined pill.
 */
export function AdDealSummary({ ad }: { ad: DealAd }) {
  const photo = ad.images?.[0];
  const sellerName = ad.user?.business_name || ad.user?.full_name;
  const verified = Boolean(ad.user?.verification_badges?.phone_verified);
  const place = localized(ad.location?.name);

  return (
    <article aria-labelledby="deal-ad-title" className="rounded-qb-xl border border-qb-line bg-qb-surface p-4 font-qb text-qb-ink">
      <div className="relative mb-3.5 h-[150px] overflow-hidden rounded-qb-lg bg-qb-line">
        {photo ? (
          <Image
            src={photo.sizes.medium || photo.sizes.thumbnail}
            alt=""
            fill
            sizes="(min-width: 1001px) 385px, 100vw"
            className="object-cover"
            preload
          />
        ) : null}
      </div>
      <h2 id="deal-ad-title" className="mb-2 line-clamp-2 text-qb-body leading-[1.3] font-semibold tracking-normal">
        <Link href={`/ads/${encodeURIComponent(ad.id)}`} dir="auto" className={cn('rounded-qb-xs', focusRing)}>
          {ad.title}
        </Link>
      </h2>
      {ad.price != null ? (
        <p className="mb-2.5 text-qb-caption text-qb-ink-secondary">
          {t('orders.deal.asking_price')} <span className="font-semibold text-qb-brand">{formatListPrice(ad.price, ad.currency)}</span>
        </p>
      ) : null}
      {place ? (
        <p className="mb-3.5 flex items-center gap-1.5 text-qb-label text-qb-ink-subtle">
          <Icon icon={LocationPinSlimIcon} size={14} />
          <span className="truncate">{place}</span>
        </p>
      ) : null}
      {sellerName ? (
        <div className="flex items-center gap-2.5 border-t border-qb-line pt-3.5">
          {/* The name follows in text, so the photo is not read out as well. */}
          <Avatar
            name={sellerName}
            src={ad.user?.avatar_url}
            decorative
            className="size-9 border border-qb-line bg-(--color-qb-avatar) text-qb-label text-qb-ink"
          />
          <p className="flex min-w-0 flex-1 items-center gap-[5px] text-qb-caption font-semibold">
            <span dir="auto" className="truncate">
              {sellerName}
            </span>
            {verified ? <VerifiedMark className="size-3.5" /> : null}
          </p>
          {verified ? (
            <span className="flex shrink-0 items-center gap-1 rounded-qb-sm border border-qb-safe-pay px-2.5 py-1 text-qb-micro font-medium text-qb-safe-pay">
              <Icon icon={ShieldCheckSafepayIcon} size={12} />
              {t('orders.deal.verified')}
            </span>
          ) : null}
        </div>
      ) : null}
    </article>
  );
}
