import Image from 'next/image';
import type { ReactNode } from 'react';

import { Avatar } from '@/components/design-system/Avatar';
import { formatCount, tCount } from '@/lib/ads/display';
import type { Locale } from '@/lib/i18n/locale';
import type { PublicUserProfile } from '@/lib/api/types';

import { VerifiedMark, isVerifiedSeller, sellerDisplayName } from './SellerBadges';

interface CompanyHeaderProps {
  profile: PublicUserProfile;
  locale: Locale;
  /** Follow and message buttons. */
  actions: ReactNode;
  /** Report / block, under the buttons. */
  secondaryActions?: ReactNode;
}

/**
 * Cover photo across the page with the company card overlapping its lower
 * edge (145:1063 / 532:27700 / 616:26793).
 */
export function CompanyHeader({ profile, locale, actions, secondaryActions }: CompanyHeaderProps) {
  const name = sellerDisplayName(profile);
  const cover = profile.business_profile?.cover_url;

  return (
    <div className="font-qb">
      <div className="relative h-[140px] overflow-hidden bg-qb-brand-soft qb-tablet:h-[184px] qb-desktop:h-[228px]">
        {cover ? <Image src={cover} alt="" fill preload fetchPriority="high" sizes="100vw" className="object-cover" /> : null}
      </div>

      <div className="mx-auto -mt-20 max-w-[1344px] px-4 qb-tablet:-mt-[92px] qb-tablet:px-6 qb-desktop:-mt-[120px] qb-desktop:px-12">
        <section
          aria-label={name}
          className="relative flex flex-col gap-5 rounded-qb-2xl border border-qb-line bg-qb-surface p-4 shadow-qb-card qb-tablet:flex-row qb-tablet:items-center qb-tablet:justify-between qb-tablet:px-6 qb-tablet:py-[25px] qb-desktop:px-5 qb-desktop:py-[17px]"
        >
          <div className="flex min-w-0 items-center gap-4 qb-desktop:gap-[18px]">
            <Avatar
              name={name}
              src={profile.avatar_url}
              size="lg"
              tone="brand"
              className="size-16 border-2 border-qb-line text-qb-h4 qb-tablet:size-20 qb-desktop:size-[120px] qb-desktop:text-qb-h2"
            />
            <div className="min-w-0">
              <h1 className="flex items-center gap-1.5 text-qb-body-lg font-semibold tracking-normal text-qb-ink qb-desktop:text-qb-h3">
                <span className="truncate">{name}</span>
                {isVerifiedSeller(profile) ? <VerifiedMark /> : null}
              </h1>
              <p className="mt-2 flex flex-wrap items-center gap-x-2 text-qb-micro text-qb-breadcrumb qb-tablet:text-qb-caption qb-desktop:mt-3 qb-desktop:text-qb-h5">
                <span>
                  <span className="font-medium text-qb-ink-body">{formatCount(profile.ads_count, locale)}</span>{' '}
                  {tCount('users.profile.ads_label', profile.ads_count, locale)}
                </span>
                <span aria-hidden="true">•</span>
                <span>
                  <span className="font-medium text-qb-ink-body">{formatCount(profile.followers_count, locale)}</span>{' '}
                  {tCount('users.profile.followers_label', profile.followers_count, locale)}
                </span>
              </p>
            </div>
          </div>
          <div className="flex shrink-0 flex-col gap-2">
            <div className="[display:grid] grid-cols-2 gap-3 qb-tablet:flex">{actions}</div>
            {secondaryActions ? <div className="flex flex-wrap justify-center gap-1 qb-tablet:justify-end">{secondaryActions}</div> : null}
          </div>
        </section>
      </div>
    </div>
  );
}
