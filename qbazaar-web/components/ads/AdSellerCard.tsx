'use client';

/**
 * Seller panel of the ad detail: who sells, a few facts about them and the
 * contact actions. The actions follow the ad's state: a sold ad says so (to
 * everyone, the owner included) and the owner sees a "this is your ad" note;
 * everyone else gets "Make an Offer" and "Send Message", which both open the
 * conversation (offers are made there). "Rate the seller" stays on a sold ad,
 * since a review needs a completed deal. "Buy Now" joins them with the
 * purchase flow (FE-16.8).
 */
import Link from 'next/link';
import { CalendarDays, LayoutGrid, MessageSquareText, Star, Tag, type LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import { Avatar } from '@/components/design-system/Avatar';
import { Badge } from '@/components/design-system/Badge';
import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { StartConversationButton } from '@/components/messaging/StartConversationButton';
import { ReviewSellerButton } from '@/components/reviews/ReviewSellerButton';
import { SellerTypeChip, VerifiedMark, isVerifiedSeller, sellerDisplayName } from '@/components/users/SellerBadges';
import { formatAdDate, formatRating } from '@/lib/ads/display';
import type { Locale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';
import { cn } from '@/lib/utils';
import type { Ad, PublicUser } from '@/lib/api/types';

import { detailCard, detailCardSide } from './detail-card';

interface AdSellerCardProps {
  ad: Pick<Ad, 'id' | 'user_id' | 'status'>;
  /** The embedded seller card; the panel still offers the actions without it. */
  seller?: PublicUser;
  isOwner: boolean;
  locale: Locale;
}

const actionButton = 'h-12 rounded-qb-lg font-medium qb-tablet:h-10 qb-tablet:rounded-qb-md';

export function AdSellerCard({ ad, seller, isOwner, locale }: AdSellerCardProps) {
  return (
    <section aria-label={t('ads.detail.seller')} className={cn(detailCard, detailCardSide)}>
      {seller ? <SellerSummary seller={seller} locale={locale} /> : null}
      <div className="mt-5 flex flex-col gap-3 first:mt-0 qb-desktop:mt-4">
        <SellerActions ad={ad} isOwner={isOwner} />
      </div>
    </section>
  );
}

function SellerSummary({ seller, locale }: { seller: PublicUser; locale: Locale }) {
  const name = sellerDisplayName(seller);

  return (
    <>
      <div className="flex items-center justify-between gap-3">
        <Link
          href={`/u/${seller.id}`}
          className={cn('flex min-w-0 items-center gap-3 rounded-qb-md qb-desktop:gap-2.5', focusRing, 'focus-visible:outline-solid')}
        >
          {/* The name follows in text, so the picture stays out of the link's name. */}
          <span aria-hidden="true" className="flex shrink-0">
            <Avatar
              name={name}
              src={seller.avatar_url}
              className="size-14 border border-qb-line bg-qb-hover text-qb-body text-qb-ink qb-tablet:size-12 qb-desktop:size-[50px]"
            />
          </span>
          <span className="flex min-w-0 flex-col gap-1.5">
            <span className="flex min-w-0 items-center gap-1">
              <span dir="auto" className="truncate text-qb-body font-semibold text-qb-ink">
                {name}
              </span>
              {isVerifiedSeller(seller) ? <VerifiedMark /> : null}
            </span>
            <SellerTypeChip accountType={seller.account_type} className="qb-desktop:hidden" />
          </span>
        </Link>
        <SellerTypeChip accountType={seller.account_type} className="hidden qb-desktop:inline-flex" />
      </div>

      <ul
        className={cn(
          'mt-4 flex flex-wrap gap-x-6 gap-y-3.5 text-qb-label text-qb-ink-subtle qb-tablet:flex-col qb-tablet:text-qb-caption',
          'qb-desktop:-mx-6 qb-desktop:border-y qb-desktop:border-qb-line qb-desktop:px-6 qb-desktop:py-4 qb-desktop:text-qb-body',
        )}
      >
        {seller.rating_count > 0 ? (
          <InfoRow icon={Star}>
            {`${formatRating(seller.rating_avg, locale)} · ${tPlural('reviews.count', seller.rating_count, locale)}`}
          </InfoRow>
        ) : null}
        <InfoRow icon={LayoutGrid}>{tPlural('ads.detail.seller_ads', seller.ads_count, locale)}</InfoRow>
        <InfoRow icon={CalendarDays}>{t('users.profile.joined', { date: formatAdDate(seller.joined_at, locale) })}</InfoRow>
      </ul>
    </>
  );
}

function SellerActions({ ad, isOwner }: Pick<AdSellerCardProps, 'ad' | 'isOwner'>) {
  const rateSeller = <ReviewSellerButton adId={ad.id} sellerId={ad.user_id} className="self-center" />;

  if (ad.status === 'sold') {
    return (
      <>
        <Badge tone="neutral" className="h-10 justify-center rounded-qb-md font-qb-label text-qb-caption">
          {t('ads.status.sold')}
        </Badge>
        {rateSeller}
      </>
    );
  }
  if (isOwner) {
    return (
      <Badge tone="brand" className="h-10 justify-center rounded-qb-md font-qb-label text-qb-caption">
        {t('messaging.own_ad_badge')}
      </Badge>
    );
  }
  return (
    <>
      <StartConversationButton ad={ad} variant="secondary" size="sm" icon={Tag} className={actionButton}>
        {t('ads.actions.make_offer')}
      </StartConversationButton>
      <StartConversationButton
        ad={ad}
        variant="ghost"
        size="sm"
        icon={MessageSquareText}
        className={cn(actionButton, 'text-qb-brand hover:bg-qb-brand-soft hover:text-qb-brand-active')}
      >
        {t('ads.actions.send_message')}
      </StartConversationButton>
      {rateSeller}
    </>
  );
}

function InfoRow({ icon, children }: { icon: LucideIcon; children: ReactNode }) {
  return (
    <li className="flex items-center gap-2">
      <Icon icon={icon} size="sm" className="qb-desktop:size-[18px]" />
      {children}
    </li>
  );
}
