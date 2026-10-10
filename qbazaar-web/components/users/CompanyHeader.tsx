import Image from 'next/image';
import type { ReactNode } from 'react';

import { Avatar } from '@/components/design-system/Avatar';
import { formatNumber } from '@/lib/i18n/format';
import type { Locale } from '@/lib/i18n/locale';
import { tPlural } from '@/lib/i18n/plural';
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
 * The company page's head (seller-organization.html with mobilemenu.js
 * sellerHero): a 200 px grey band across the page, the cover inside the
 * content width with r16 top corners (190 px, 140 on phones), and the r20
 * company card pulled 100 px up over it. The round logo rises out of the
 * card; name and stats sit beside it and the buttons on the end side. On
 * phones the card wraps: logo and name, the stats, then the buttons.
 */
export function CompanyHeader({ profile, locale, actions, secondaryActions }: CompanyHeaderProps) {
  const name = sellerDisplayName(profile);
  const cover = profile.business_profile?.cover_url;

  return (
    <div className="relative font-qb">
      <div aria-hidden="true" className="absolute inset-x-0 top-0 h-[200px] bg-qb-band" />
      <div className="relative mx-auto max-w-[1440px] px-qb-gutter pt-[30px]">
        <div className="relative h-[140px] overflow-hidden rounded-t-qb-xl bg-qb-brand-soft qb-tablet:h-[190px]">
          {cover ? <Image src={cover} alt="" fill preload fetchPriority="high" sizes="(min-width: 1440px) 1360px, 100vw" className="object-cover" /> : null}
        </div>

        <section
          aria-label={name}
          className="relative -mt-[100px] flex flex-wrap items-center justify-between gap-[18px] rounded-[20px] border border-qb-line bg-qb-surface p-6 shadow-qb-card qb-tablet:grid qb-tablet:grid-cols-[auto_minmax(0,1fr)_auto] qb-tablet:gap-y-0 qb-desktop:px-[clamp(24px,3vw,40px)] qb-desktop:py-[clamp(24px,3vw,36px)]"
        >
          <Avatar
            name={name}
            src={profile.avatar_url}
            decorative
            className="-mt-[50px] size-[76px] border-4 border-qb-surface bg-qb-surface text-[26px] text-qb-ink-logo shadow-qb-logo qb-tablet:row-span-2 qb-tablet:-mt-16 qb-tablet:size-24"
          />
          {/* Only the name wraps, never before the inline badge, so a long name keeps it after its last word. */}
          <h1 className="min-w-0 text-[clamp(24px,3vw,30px)] font-semibold tracking-normal break-words whitespace-nowrap text-qb-ink qb-tablet:col-start-2 qb-tablet:self-end">
            <bdi className="whitespace-normal">{name}</bdi>
            {isVerifiedSeller(profile) ? <VerifiedMark className="ms-2.5 inline-block size-[22px] align-[-2px]" /> : null}
          </h1>
          <p className="basis-full text-[13.5px] text-qb-icon-muted qb-tablet:col-start-2 qb-tablet:row-start-2 qb-tablet:mt-[5px] qb-tablet:self-start qb-tablet:text-qb-h5">
            {formatNumber(profile.ads_count, locale)}{' '}
            <span className="text-qb-ink-disabled">{tPlural('users.profile.ads_label', profile.ads_count, locale)}</span>
            <span aria-hidden="true"> &nbsp;•&nbsp; </span>
            {formatNumber(profile.followers_count, locale)} {tPlural('users.profile.followers_label', profile.followers_count, locale)}
          </p>
          <div className="flex flex-col gap-2 qb-tablet:col-start-3 qb-tablet:row-span-2 qb-tablet:row-start-1">
            <div className="flex flex-wrap gap-3">{actions}</div>
            {secondaryActions ? <div className="flex flex-wrap justify-center gap-1 qb-tablet:justify-end">{secondaryActions}</div> : null}
          </div>
        </section>
      </div>
    </div>
  );
}
