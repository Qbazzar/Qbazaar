import type { ReactNode } from 'react';
import { CalendarDays } from 'lucide-react';

import { Badge } from '@/components/design-system/Badge';
import { Icon } from '@/components/design-system/Icon';
import { intlLocale } from '@/lib/i18n/format';
import { getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { AccountType } from '@/lib/api/types';

import { NamedAvatar } from './NamedAvatar';

export interface ProfileSummaryProps {
  name: string;
  avatarUrl?: string | null;
  accountType: AccountType;
  /** ISO date the account was created. */
  joinedAt?: string | null;
  /** Line under the name ("You have 3 ads", the email). */
  subtitle?: ReactNode;
  className?: string;
}

/** Profile header card of My Ads (518:20536): avatar, name, seller-type badge and "Active since". */
export function ProfileSummary({ name, avatarUrl, accountType, joinedAt, subtitle, className }: ProfileSummaryProps) {
  const since = formatJoinDate(joinedAt);
  return (
    <div
      className={cn(
        'rounded-qb-2xl border border-qb-line bg-qb-surface p-[15px] font-qb shadow-qb-card qb-tablet:px-8 qb-tablet:py-8',
        className,
      )}
    >
      <div className="flex items-center gap-2.5 qb-tablet:gap-4">
        <NamedAvatar name={name} src={avatarUrl} size="lg" className="size-[52px] qb-tablet:size-[78px]" />
        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
            <p className="truncate text-qb-body font-medium text-qb-ink qb-tablet:text-qb-h3">{name}</p>
            <AccountTypeBadge accountType={accountType} />
          </div>
          {subtitle ? (
            <p className="mt-1.5 truncate text-qb-caption text-qb-ink-subtle qb-tablet:mt-2.5 qb-tablet:text-qb-body">{subtitle}</p>
          ) : null}
        </div>
      </div>
      {since ? (
        <p className="mt-5 flex items-center gap-2 text-qb-caption text-qb-ink-subtle qb-tablet:mt-6 qb-tablet:text-qb-body">
          <Icon icon={CalendarDays} />
          {t('account.profile.active_since', { date: since })}
        </p>
      ) : null}
    </div>
  );
}

/** "Private Seller" / "Business" pill, set in the label face like the design's status chips. */
export function AccountTypeBadge({ accountType, className }: { accountType: AccountType; className?: string }) {
  return (
    <Badge
      tone="brand"
      size="sm"
      className={cn('rounded-qb-xs border border-qb-brand px-2 py-[3px] font-qb-label text-qb-tiny qb-tablet:text-qb-micro', className)}
    >
      {t(accountType === 'business' ? 'account.profile.business_seller' : 'account.profile.private_seller')}
    </Badge>
  );
}

function formatJoinDate(iso: string | null | undefined): string | null {
  if (!iso) return null;
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return null;
  return new Intl.DateTimeFormat(getLocale() === 'ar' ? intlLocale('ar') : 'en-GB', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(date);
}
