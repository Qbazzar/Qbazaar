import type { ReactNode } from 'react';
import Link from 'next/link';
import { CalendarDays, CircleCheck, Users } from 'lucide-react';

import { focusRing } from '@/components/design-system/focus-ring';
import { intlLocale } from '@/lib/i18n/format';
import { getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';
import { cn } from '@/lib/utils';
import type { AccountType } from '@/lib/api/types';

import { NamedAvatar } from './NamedAvatar';

export interface ProfileSummaryProps {
  name: string;
  avatarUrl?: string | null;
  accountType: AccountType;
  /** ISO date the account was created. */
  joinedAt?: string | null;
  /** Line under the name ("You have 3 ads online"). */
  subtitle?: ReactNode;
  /** "3 Followers • 20 Following", once the public profile has loaded. */
  follows?: { followers: number; following: number };
  className?: string;
}

/**
 * Profile card at the top of My Ads (acctShowDashboard, 518:20536): avatar,
 * name and seller chip, the stats line, and the "Overview of your sales"
 * tile on the end side. Phones stack the stats and put the tile under them
 * (600:29942).
 */
export function ProfileSummary({
  name,
  avatarUrl,
  accountType,
  joinedAt,
  subtitle,
  follows,
  className,
}: ProfileSummaryProps) {
  const since = formatJoinDate(joinedAt);
  return (
    <div
      className={cn(
        'relative flex flex-wrap items-start justify-between gap-6 rounded-qb-2xl border border-qb-line bg-qb-surface p-4 font-qb shadow-qb-acct-card qb-tablet:p-7',
        className,
      )}
    >
      <div className="min-w-0 flex-[1_1_340px]">
        <div className="mb-[18px] flex items-center gap-4">
          <NamedAvatar
            name={name}
            src={avatarUrl}
            size="lg"
            className="size-[66px] text-[22px] font-semibold"
          />
          <div className="min-w-0">
            <div className="flex flex-wrap items-center gap-2.5">
              <p className="text-[22px] font-semibold text-qb-ink">{name}</p>
              <AccountTypeBadge
                accountType={accountType}
                className="absolute end-4 top-4 qb-tablet:static"
              />
            </div>
            {subtitle ? <p className="mt-1 text-qb-body-sm text-qb-ink-subtle">{subtitle}</p> : null}
          </div>
        </div>
        <ul className="flex flex-col gap-2 text-qb-body-sm text-qb-ink-secondary qb-tablet:flex-row qb-tablet:flex-wrap qb-tablet:gap-x-[26px]">
          {since ? (
            <li className="flex items-center gap-2">
              <CalendarDays aria-hidden="true" className="size-[17px] shrink-0" strokeWidth={1.6} />
              {t('account.profile.active_since', { date: since })}
            </li>
          ) : null}
          {follows ? (
            <li className="flex items-center gap-2">
              <Users aria-hidden="true" className="size-[17px] shrink-0" strokeWidth={1.6} />
              {`${tPlural('account.my_ads.followers', follows.followers)} • ${tPlural('account.my_ads.following', follows.following)}`}
            </li>
          ) : null}
        </ul>
      </div>
      <Link
        href="/account/orders?role=seller"
        className={cn(
          'flex w-full items-center gap-3.5 rounded-qb-xl bg-qb-acct-tile px-5 py-[18px] qb-tablet:w-auto qb-tablet:min-w-[240px] qb-tablet:flex-[0_1_320px]',
          focusRing,
        )}
      >
        <CircleCheck aria-hidden="true" className="size-6 shrink-0 text-qb-brand" strokeWidth={1.6} />
        <span>
          <span className="block text-qb-body font-medium text-qb-ink-title">{t('account.my_ads.sales_title')}</span>
          <span className="mt-0.5 block text-qb-caption text-qb-ink-subtle">{t('account.my_ads.sales_body')}</span>
        </span>
      </Link>
    </div>
  );
}

/**
 * "Private Seller" / "Business Seller" chip: Montserrat 500 10 px on the soft
 * orange, as typo.js sets that exact string.
 */
export function AccountTypeBadge({ accountType, className }: { accountType: AccountType; className?: string }) {
  return (
    <span
      className={cn(
        'inline-flex items-center rounded-qb-sm bg-qb-brand-soft px-3 py-1 font-qb-label text-qb-tiny font-medium whitespace-nowrap text-qb-brand',
        className,
      )}
    >
      {t(accountType === 'business' ? 'account.profile.business_seller' : 'account.profile.private_seller')}
    </span>
  );
}

function formatJoinDate(iso: string | null | undefined): string | null {
  if (!iso) return null;
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return null;
  if (getLocale() === 'ar') {
    return new Intl.DateTimeFormat(intlLocale('ar'), { day: '2-digit', month: '2-digit', year: 'numeric' }).format(date);
  }
  // The design writes "08.01.2016".
  const pad = (value: number) => String(value).padStart(2, '0');
  return `${pad(date.getDate())}.${pad(date.getMonth() + 1)}.${date.getFullYear()}`;
}
