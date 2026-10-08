'use client';

/**
 * Public seller page. Private sellers get the profile card with their ads as
 * rows (136:1562 / 548:42879 / 625:31486); business accounts the company
 * layout: cover, company card, info sidebar and the Ads / About us / Legal
 * Info tabs (145:1063 / 532:27700 / 616:26793). Reviews follow the ads.
 */
import { useState } from 'react';
import { MessageSquareText } from 'lucide-react';
import { toast } from 'sonner';

import { Breadcrumb } from '@/components/design-system/Breadcrumb';
import { Badge } from '@/components/design-system/Badge';
import { Button } from '@/components/design-system/Button';
import { pageFrame } from '@/components/design-system/page-frame';
import { ReportButton } from '@/components/reports/ReportButton';
import { SellerReviews } from '@/components/reviews/SellerReviews';
import { useAuth } from '@/hooks/useAuth';
import type { Locale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';
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

export function SellerProfileView({ profile, locale, viewerStateKnown, defaultTab }: SellerProfileViewProps) {
  const { user } = useAuth();
  const [blocked, setBlocked] = useState(false);
  const name = sellerDisplayName(profile);
  const isSelf = user?.id === profile.id;
  const isBusiness = profile.account_type === 'business';
  const crumbs = [
    { label: t('home.breadcrumb'), href: '/' },
    ...(isBusiness ? [{ label: t('companies.title'), href: '/companies' }] : []),
    { label: name },
  ];

  const actions = isSelf ? null : (
    <>
      <FollowButton
        userId={profile.id}
        name={name}
        isFollowing={profile.is_following}
        stateKnown={viewerStateKnown}
        label={isBusiness ? undefined : t('users.follow.follow_seller')}
        size="sm"
        className="h-10 w-full qb-tablet:w-auto qb-tablet:min-w-[106px]"
      />
      {/* Conversations belong to an ad; a seller-level thread isn't in the API yet. */}
      <Button
        variant="secondary"
        size="sm"
        onClick={() => toast.info(t('users.profile.send_message_soon'))}
        className="h-10 w-full font-medium qb-tablet:w-auto qb-tablet:min-w-[106px]"
      >
        <MessageSquareText aria-hidden />
        {isBusiness ? t('users.profile.message') : t('ads.actions.send_message')}
      </Button>
    </>
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
    return (
      <main className="bg-qb-page pb-16 font-qb text-qb-ink">
        <div className={`${pageFrame} hidden pt-[72px] pb-[49px] qb-tablet:block qb-desktop:pt-[65px]`}>
          <Breadcrumb items={crumbs} />
        </div>
        <CompanyHeader profile={profile} locale={locale} actions={actions} secondaryActions={secondaryActions} />
        <div className="mx-auto mt-6 flex max-w-[1344px] items-start gap-6 px-4 qb-tablet:mt-8 qb-tablet:px-6 qb-desktop:mt-12 qb-desktop:px-12">
          {hasCompanyContacts(business) ? (
            <CompanyInfoCard business={business} locale={locale} className="hidden w-[356px] shrink-0 qb-desktop:block" />
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

  return (
    <main className="bg-qb-page pb-16 font-qb text-qb-ink">
      <div className={`${pageFrame} pt-10 qb-tablet:pt-[72px] qb-desktop:pt-[65px]`}>
        <Breadcrumb items={crumbs} className="hidden qb-tablet:block" />
        <div className="flex flex-col gap-6 qb-tablet:mt-[65px] qb-tablet:gap-[46px] qb-desktop:flex-row qb-desktop:items-start qb-desktop:gap-9">
          <SellerProfileCard
            profile={profile}
            locale={locale}
            actions={actions}
            secondaryActions={secondaryActions}
            className="qb-desktop:w-[356px] qb-desktop:shrink-0"
          />
          <div className="min-w-0 flex-1">
            <div className="mb-6 qb-tablet:mb-[30px]">
              <h2 className="text-qb-body-lg font-medium tracking-normal text-qb-ink-title qb-tablet:text-qb-h4">
                {t('users.profile.active_listings')}
              </h2>
              <p className="mt-2 text-qb-body text-qb-ink-subtle">
                {tPlural('users.profile.ads_available', profile.ads_count, locale)}
              </p>
            </div>
            <SellerListings userId={profile.id} sellerName={name} layout="rows" />
            {reviews}
          </div>
        </div>
      </div>
    </main>
  );
}
