'use client';

/**
 * Public seller page. Private sellers get the profile card beside their ads
 * as rows (seller-individual.html); business accounts the company layout:
 * grey band, cover, company card, the Info sidebar and the Ads / About us /
 * Legal Info tabs (seller-organization.html). Reviews follow the ads.
 */
import { useState } from 'react';
import { MessageCircle, Star } from 'lucide-react';
import { toast } from 'sonner';

import { Breadcrumb } from '@/components/design-system/Breadcrumb';
import { Badge } from '@/components/design-system/Badge';
import { Button } from '@/components/design-system/Button';
import { ReportButton } from '@/components/reports/ReportButton';
import { SellerReviews } from '@/components/reviews/SellerReviews';
import { useAuth } from '@/hooks/useAuth';
import type { Locale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';
import { cn } from '@/lib/utils';
import type { PublicUserProfile } from '@/lib/api/types';

import { BlockUserButton } from './BlockUserButton';
import { CompanyHeader } from './CompanyHeader';
import { CompanyInfoCard, hasCompanyContacts } from './CompanyInfoCard';
import { CompanyTabs, type CompanyTab } from './CompanyTabs';
import { FollowButton } from './FollowButton';
import { SellerListings } from './SellerListings';
import { SellerProfileCard } from './SellerProfileCard';
import { sellerDisplayName } from './SellerBadges';

interface SellerProfileViewProps {
  profile: PublicUserProfile;
  locale: Locale;
  /** False until the profile was fetched with the viewer's session (follow state). */
  viewerStateKnown: boolean;
  defaultTab: CompanyTab;
}

/** The outlined message button keeps its look under the pointer; only orange buttons react. */
const messageButton = 'border-qb-brand bg-qb-surface text-qb-brand hover:bg-qb-surface hover:text-qb-brand';

export function SellerProfileView({ profile, locale, viewerStateKnown, defaultTab }: SellerProfileViewProps) {
  const { user } = useAuth();
  const [blocked, setBlocked] = useState(false);
  const name = sellerDisplayName(profile);
  const isSelf = user?.id === profile.id;
  const isBusiness = profile.account_type === 'business';
  // Conversations belong to an ad; a seller-level thread isn't in the API yet.
  const messageSoon = () => toast.info(t('users.profile.send_message_soon'));

  const followButton = (className: string, label?: string) => (
    <FollowButton
      userId={profile.id}
      name={name}
      isFollowing={profile.is_following}
      stateKnown={viewerStateKnown}
      label={label}
      icon={label ? undefined : Star}
      size="sm"
      className={className}
    />
  );

  const secondaryActions = isSelf ? null : (
    <>
      <ReportButton target_type="user" target_id={profile.id} />
      {blocked ? (
        <Badge tone="danger" size="sm">
          {t('users.block.success')}
        </Badge>
      ) : (
        <BlockUserButton userId={profile.id} userName={name} onBlocked={() => setBlocked(true)} />
      )}
    </>
  );

  const reviews = (
    <SellerReviews userId={profile.id} ratingAvg={profile.rating_avg} ratingCount={profile.rating_count} className="mt-10" />
  );

  if (isBusiness) {
    const business = profile.business_profile;
    // 124 x 43 / 147 x 43: 15 px / 500 with 17 px icons and 26 px side padding.
    const companyButton = 'h-[43px] gap-2 rounded-qb-md px-[26px] text-qb-body-sm font-medium [&_svg]:size-[17px]';
    const actions = isSelf ? null : (
      <>
        {followButton(companyButton)}
        <Button variant="secondary" size="sm" onClick={messageSoon} className={cn(companyButton, messageButton)}>
          <MessageCircle aria-hidden strokeWidth={1.7} />
          {t('users.profile.message')}
        </Button>
      </>
    );

    return (
      <main className="bg-qb-page pb-10 font-qb text-qb-ink">
        <CompanyHeader profile={profile} locale={locale} actions={actions} secondaryActions={secondaryActions} />
        <div className="mx-auto mt-6 flex max-w-[1440px] items-start gap-6 px-qb-gutter">
          {hasCompanyContacts(business) ? (
            <CompanyInfoCard business={business} locale={locale} className="hidden w-[340px] shrink-0 qb-desktop:block" />
          ) : null}
          <div className="min-w-0 flex-1">
            <CompanyTabs
              business={business}
              adsCount={profile.ads_count}
              locale={locale}
              defaultTab={defaultTab}
              ads={<SellerListings userId={profile.id} sellerName={name} layout="grid" />}
            />
            {reviews}
          </div>
        </div>
      </main>
    );
  }

  const sellerButton = 'h-10 w-full rounded-qb-md px-2.5';
  const actions = isSelf ? null : (
    <>
      {followButton(cn(sellerButton, 'text-qb-body-sm font-semibold'), t('users.follow.follow_seller'))}
      <Button variant="secondary" size="sm" onClick={messageSoon} className={cn(sellerButton, messageButton, 'text-qb-caption font-medium')}>
        {t('ads.actions.send_message')}
      </Button>
    </>
  );

  return (
    <main className="bg-qb-page pb-16 font-qb text-qb-ink">
      <div className="mx-auto max-w-[1440px] px-qb-gutter pt-[clamp(20px,4vw,40px)]">
        <Breadcrumb items={[{ label: t('home.breadcrumb'), href: '/' }, { label: name }]} className="mb-[22px]" />
        <div className="flex flex-col gap-6 qb-desktop:flex-row qb-desktop:items-start">
          <SellerProfileCard
            profile={profile}
            locale={locale}
            actions={actions}
            secondaryActions={secondaryActions}
            className="qb-desktop:w-[356px] qb-desktop:shrink-0"
          />
          <div className="flex min-w-0 flex-1 flex-col gap-[18px]">
            <div>
              <h2 className="text-qb-h4 font-medium tracking-normal text-qb-ink-title">{t('users.profile.active_listings')}</h2>
              <p className="mt-1 text-qb-body text-qb-ink-label">{tPlural('users.profile.ads_available', profile.ads_count, locale)}</p>
            </div>
            <SellerListings userId={profile.id} sellerName={name} layout="rows" />
            {reviews}
          </div>
        </div>
      </div>
    </main>
  );
}
