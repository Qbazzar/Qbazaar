'use client';

/**
 * Profile card of a private seller (seller-individual.html): avatar, name,
 * follow and message, the Info rows and the highlight chips, in a white r24
 * card with 32 / 16 px padding and the canonical card shadow.
 */
import { CalendarDays, MailCheck, ShieldCheck, Star, Tag, UserPlus, type LucideIcon } from 'lucide-react';
import { useId, type ReactNode } from 'react';

import { Avatar } from '@/components/design-system/Avatar';
import { Icon } from '@/components/design-system/Icon';
import { formatDayMonthYear } from '@/lib/ads/dates';
import { formatRating } from '@/lib/ads/display';
import { formatNumber } from '@/lib/i18n/format';
import type { Locale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';
import { cn } from '@/lib/utils';
import type { PublicUserProfile } from '@/lib/api/types';

import { VerifiedMark, isVerifiedSeller, sellerDisplayName } from './SellerBadges';

interface SellerProfileCardProps {
  profile: PublicUserProfile;
  locale: Locale;
  /** Follow and message buttons; none on your own profile. */
  actions: ReactNode;
  /** Report / block, shown under the buttons. */
  secondaryActions?: ReactNode;
  className?: string;
}

/** The 18 px / 500 section titles of the card ("Info", "Seller Highlights"). */
const sectionTitle = 'mb-3.5 text-qb-body-lg font-medium tracking-normal text-qb-ink';

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
          label: `${formatRating(profile.rating_avg, locale)} · ${tPlural('reviews.count', profile.rating_count, locale)}`,
        }
      : null,
  ].filter((item): item is { icon: LucideIcon; label: string } => item !== null);

  return (
    <section
      aria-label={name}
      className={cn('rounded-qb-2xl border border-qb-line bg-qb-surface px-4 py-8 font-qb shadow-qb-card', className)}
    >
      <div className="flex flex-col items-center gap-4">
        <div className="flex flex-col items-center gap-2 text-center">
          <Avatar
            name={name}
            src={profile.avatar_url}
            decorative
            className="size-[79px] border border-qb-line bg-qb-avatar text-qb-h3 text-qb-ink"
          />
          <h1 className="flex items-center gap-1.5 text-qb-h3 font-medium tracking-normal text-qb-ink">
            <span dir="auto">{name}</span>
            {isVerifiedSeller(profile) ? <VerifiedMark className="size-[22px]" /> : null}
          </h1>
          <p className="font-qb-label text-qb-tiny font-medium text-qb-ink-label">{t(`users.profile.seller_type.${profile.account_type}`)}</p>
        </div>
        {actions ? <div className="[display:grid] w-full grid-cols-2 gap-[11px]">{actions}</div> : null}
      </div>
      {secondaryActions ? <div className="mt-3 flex flex-wrap items-center justify-center gap-1">{secondaryActions}</div> : null}

      <section aria-labelledby={infoTitleId} className="my-6 border-t border-qb-line pt-6">
        <h2 id={infoTitleId} className={sectionTitle}>
          {t('users.profile.info')}
        </h2>
        <dl className="flex flex-col gap-3.5 text-qb-body">
          <InfoRow icon={UserPlus} label={t('users.profile.followers')} value={formatNumber(profile.followers_count, locale)} />
          <InfoRow icon={CalendarDays} label={t('users.profile.member_since')} value={formatDayMonthYear(profile.joined_at, locale)} />
          <InfoRow icon={Tag} label={t('users.profile.ads_number')} value={formatNumber(profile.ads_count, locale)} />
        </dl>
      </section>

      {highlights.length ? (
        <section aria-labelledby={highlightsTitleId} className="border-t border-qb-line pt-6">
          <h2 id={highlightsTitleId} className={sectionTitle}>
            {t('users.profile.highlights')}
          </h2>
          <ul className="flex flex-wrap gap-3">
            {highlights.map((item) => (
              <li
                key={item.label}
                className="flex items-center gap-[5px] rounded-qb-sm bg-qb-brand-faint px-4 py-2 text-qb-body-sm text-qb-brand"
              >
                <Icon icon={item.icon} size="sm" strokeWidth={1.7} />
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
      <dt className="flex items-center gap-1.5 text-qb-ink-label">
        <Icon icon={icon} size="sm" strokeWidth={1.7} />
        {label}
      </dt>
      <dd className="font-medium text-qb-ink">{value}</dd>
    </div>
  );
}
