'use client';

/**
 * Seller panel of the ad detail: who sells, a few facts about them and the
 * contact actions (88:776). The actions follow the ad's state: a sold ad says
 * so (to everyone, the owner included) and the owner sees a "this is your ad"
 * note. Everyone else gets "Buy Now" and "Make an Offer" when the ad takes
 * them (the API's rules in lib/orders), and "Send Message", which opens the
 * conversation. "Rate the seller" stays on a sold ad, since a review needs a
 * completed deal.
 */
import Link from 'next/link';
import { CalendarDays, MessageSquareText, ShoppingBag, Tag, type LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import { Avatar } from '@/components/design-system/Avatar';
import { Badge } from '@/components/design-system/Badge';
import { buttonVariants } from '@/components/design-system/Button';
import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { StartConversationButton } from '@/components/messaging/StartConversationButton';
import { ReviewSellerButton } from '@/components/reviews/ReviewSellerButton';
import { SellerTypeChip, VerifiedMark, isVerifiedSeller, sellerDisplayName } from '@/components/users/SellerBadges';
import { formatDottedDate } from '@/lib/ads/dates';
import { formatRating } from '@/lib/ads/display';
import type { Locale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';
import { buyNowBlocker, offerBlocker } from '@/lib/orders/offers';
import { cn } from '@/lib/utils';
import type { DealAd } from '@/lib/api/commerce-types';
import type { PublicUser } from '@/lib/api/types';

import { detailCard, detailCardSide } from './detail-card';

interface AdSellerCardProps {
  ad: DealAd;
  /** The embedded seller card; the panel still offers the actions without it. */
  seller?: PublicUser;
  isOwner: boolean;
  locale: Locale;
}

/**
 * The reference's 52 px, r10, 14 px / 500 action buttons. Only the orange
 * one reacts to the pointer (polish.css darkens it); the outlined and the
 * text button keep their look.
 */
const actionButton = 'h-auto min-h-[52px] w-full rounded-qb-md px-3.5 text-qb-caption font-medium [&_svg]:size-[18px]';

export function AdSellerCard({ ad, seller, isOwner, locale }: AdSellerCardProps) {
  return (
    <section aria-label={t('ads.detail.seller')} className={cn(detailCard, detailCardSide)}>
      {seller ? <SellerSummary seller={seller} locale={locale} /> : null}
      <div className="mt-5 flex flex-col gap-3 first:mt-0">
        <SellerActions ad={ad} isOwner={isOwner} />
      </div>
    </section>
  );
}

function SellerSummary({ seller, locale }: { seller: PublicUser; locale: Locale }) {
  const name = sellerDisplayName(seller);

  return (
    <>
      {/* In the 260 px tablet sidebar the chip drops under the name rather than squeezing it. */}
      <div className="mb-[18px] flex flex-wrap items-center justify-between gap-2.5">
        <Link href={`/u/${seller.id}`} className={cn('flex min-w-0 items-center gap-3 rounded-qb-md', focusRing)}>
          {/* The name follows in text, so the picture stays out of the link's name. */}
          <Avatar name={name} src={seller.avatar_url} tone="brand" decorative className="size-11 text-qb-body" />
          <span className="flex min-w-0 items-center gap-1.5">
            <span dir="auto" className="min-w-0 text-[17px] font-semibold text-qb-ink">
              {name}
            </span>
            {isVerifiedSeller(seller) ? <VerifiedMark className="size-4" /> : null}
          </span>
        </Link>
        <SellerTypeChip accountType={seller.account_type} />
      </div>

      <ul className="flex flex-col gap-4 border-t border-qb-line pt-[18px] text-qb-body-sm text-qb-ink-secondary">
        {seller.rating_count > 0 ? (
          <InfoRow icon={MessageSquareText}>
            {`${formatRating(seller.rating_avg, locale)} · ${tPlural('reviews.count', seller.rating_count, locale)}`}
          </InfoRow>
        ) : null}
        <InfoRow icon={Tag}>{tPlural('ads.detail.seller_ads', seller.ads_count, locale)}</InfoRow>
        <InfoRow icon={CalendarDays}>{t('ads.detail.active_since', { date: formatDottedDate(seller.joined_at) })}</InfoRow>
      </ul>
    </>
  );
}

function SellerActions({ ad, isOwner }: Pick<AdSellerCardProps, 'ad' | 'isOwner'>) {
  const rateSeller = <ReviewSellerButton adId={ad.id} sellerId={ad.user_id} className="self-center" />;

  if (ad.status === 'sold') {
    return (
      <>
        <Badge tone="neutral" font="label" className="min-h-[52px] justify-center rounded-qb-md text-qb-caption">
          {t('ads.status.sold')}
        </Badge>
        {rateSeller}
      </>
    );
  }
  if (isOwner) {
    return (
      <Badge tone="brand" font="label" className="min-h-[52px] justify-center rounded-qb-md text-qb-caption">
        {t('messaging.own_ad_badge')}
      </Badge>
    );
  }
  // The owner never gets here, so only the ad itself decides.
  const canBuy = buyNowBlocker(ad, undefined) === null;
  const canOffer = offerBlocker(ad, undefined) === null;

  return (
    <>
      {canBuy ? (
        <Link href={`/ads/${ad.id}/buy`} className={cn(buttonVariants({ size: 'sm' }), actionButton)}>
          <ShoppingBag aria-hidden />
          {t('ads.actions.buy_now')}
        </Link>
      ) : null}
      {canOffer ? (
        <Link
          href={`/ads/${ad.id}/offer`}
          className={cn(buttonVariants({ variant: 'secondary', size: 'sm' }), actionButton, 'hover:bg-qb-surface hover:text-qb-brand')}
        >
          <Tag aria-hidden />
          {t('ads.actions.make_offer')}
        </Link>
      ) : null}
      <StartConversationButton
        ad={ad}
        variant="ghost"
        size="sm"
        icon={MessageSquareText}
        className={cn(actionButton, 'text-qb-brand hover:bg-transparent')}
      >
        {t('ads.actions.send_message')}
      </StartConversationButton>
      {rateSeller}
    </>
  );
}

/** One fact about the seller with its 18 px grey line icon. */
function InfoRow({ icon, children }: { icon: LucideIcon; children: ReactNode }) {
  return (
    <li className="flex items-center gap-3">
      <Icon icon={icon} strokeWidth={1.7} className="size-[18px] text-qb-ink-subtle" />
      <span className="min-w-0">{children}</span>
    </li>
  );
}
