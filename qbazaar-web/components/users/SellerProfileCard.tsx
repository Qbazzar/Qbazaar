'use client';

/**
 * Profile card of a private seller (136:1562 / 548:42879 / 625:31486):
 * avatar, name, follow and message, the info rows and the highlight chips.
 */
import { CalendarDays, LayoutGrid, MailCheck, ShieldCheck, Star, Users, type LucideIcon } from 'lucide-react';
import { useId, type ReactNode } from 'react';

import { Avatar } from '@/components/design-system/Avatar';
import { Icon } from '@/components/design-system/Icon';
import { formatAdDate, formatCount, tCount } from '@/lib/ads/display';
import type { Locale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { PublicUserProfile } from '@/lib/api/types';

import { VerifiedMark, isVerifiedSeller, sellerDisplayName } from './SellerBadges';

interface SellerProfileCardProps {
  profile: PublicUserProfile;
  locale: Locale;
  /** Follow and message buttons. */
  actions: ReactNode;
  /** Report / block, shown under the buttons. */
  secondaryActions?: ReactNode;
  className?: string;
}

export function SellerProfileCard({ profile, locale, actions, secondaryActions, className }: SellerProfileCardProps) {
  const name = sellerDisplayName(profile);
  const infoTitleId = useId();
  const highlightsTitleId = useId();
  const badges = profile.verification_badges;
  const highlights = [
    badges?.phone_verified ? { icon: ShieldCheck, label: t('users.profile.verified_phone') } : null,
    badges?.email_verified ? { icon: MailCheck, label: t('users.profile.verified_email') } : null,
    profile.rating_count > 0
      ? {
          icon: Star,
          label: tCount('ads.detail.seller_rating', profile.rating_count, locale, {
            rating: formatCount(Math.round(profile.rating_avg * 10) / 10, locale),
          }),
        }
      : null,
  ].filter((item): item is { icon: LucideIcon; label: string } => item !== null);

  return (
    <section
      aria-label={name}
      className={cn(
        'rounded-qb-2xl border border-qb-line bg-qb-surface px-5 pt-5 pb-6 font-qb shadow-qb-card',
        'qb-tablet:px-12 qb-tablet:pt-7 qb-tablet:pb-9 qb-desktop:px-[17px] qb-desktop:pt-[42px] qb-desktop:pb-9',
        className,
      )}
    >
      <div className="flex flex-col items-center text-center">
        <Avatar name={name} src={profile.avatar_url} size="lg" className="size-20 border border-qb-line bg-qb-hover text-qb-h3 text-qb-ink qb-tablet:size-[79px]" />
        <h1 className="mt-3 flex items-center gap-1.5 text-qb-h5 font-medium tracking-normal text-qb-ink qb-tablet:mt-[14px] qb-tablet:text-qb-h4">
          {name}
          {isVerifiedSeller(profile) ? <VerifiedMark /> : null}
        </h1>
        <p className="mt-3 font-qb-label text-qb-caption font-medium text-qb-ink-subtle qb-tablet:text-qb-body">
          {t(`users.profile.seller_type.${profile.account_type}`)}
        </p>
      </div>

      <div className="mt-[22px] [display:grid] grid-cols-2 gap-3 qb-tablet:gap-5 qb-desktop:mt-9 qb-desktop:gap-[11px]">{actions}</div>
      {secondaryActions ? <div className="mt-3 flex flex-wrap items-center justify-center gap-1">{secondaryActions}</div> : null}

      <section aria-labelledby={infoTitleId} className="mt-6 qb-desktop:mt-[29px]">
        <h2 id={infoTitleId} className="text-qb-body-lg font-medium tracking-normal text-qb-ink">
          {t('users.profile.info')}
        </h2>
        <dl className="mt-[18px] flex flex-col gap-3.5 text-qb-body">
          <InfoRow icon={Users} label={t('users.profile.followers')} value={formatCount(profile.followers_count, locale)} />
          <InfoRow icon={CalendarDays} label={t('users.profile.member_since')} value={formatAdDate(profile.joined_at, locale)} />
          <InfoRow icon={LayoutGrid} label={t('users.profile.ads_number')} value={formatCount(profile.ads_count, locale)} />
        </dl>
      </section>

      {highlights.length ? (
        <section aria-labelledby={highlightsTitleId} className="mt-7 border-t border-qb-line pt-7 qb-desktop:mt-9 qb-desktop:pt-8">
          <h2 id={highlightsTitleId} className="text-qb-body-lg font-medium tracking-normal text-qb-ink">
            {t('users.profile.highlights')}
          </h2>
          <ul className="mt-4 flex flex-wrap gap-2 qb-tablet:gap-3">
            {highlights.map((item) => (
              <li
                key={item.label}
                className="flex h-9 items-center gap-1.5 rounded-qb-sm bg-qb-brand-soft px-3.5 text-qb-label text-qb-brand qb-tablet:text-qb-body"
              >
                <Icon icon={item.icon} size="sm" />
                {item.label}
              </li>
            ))}
          </ul>
        </section>
      ) : null}
    </section>
  );
}

function InfoRow({ icon, label, value }: { icon: LucideIcon; label: string; value: string }) {
  return (
    <div className="flex items-center justify-between gap-4">
      <dt className="flex items-center gap-1.5 text-qb-ink-subtle">
        <Icon icon={icon} size="sm" />
        {label}
      </dt>
      <dd className="font-medium text-qb-ink">{value}</dd>
    </div>
  );
}
