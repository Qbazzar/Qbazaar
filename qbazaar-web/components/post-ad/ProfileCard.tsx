'use client';

import type { ReactNode } from 'react';
import { CalendarDays, Check, LoaderCircle, Tag } from 'lucide-react';

import { Avatar } from '@/components/design-system/Avatar';
import { Button } from '@/components/design-system/Button';
import { cardVariants } from '@/components/design-system/Card';
import { Icon } from '@/components/design-system/Icon';
import { VerifiedMark } from '@/components/users/SellerBadges';
import type { AccountType } from '@/lib/api/types';
import { getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';
import { formatDate } from '@/lib/post-ad/format';
import { cn } from '@/lib/utils';

import '@/styles/design-tokens-sell.css';

export interface SellerSummary {
  name: string;
  avatarUrl: string | null;
  accountType: AccountType;
  verified: boolean;
  /** Live ads; undefined while loading. */
  adsCount: number | undefined;
  memberSince: string;
}

export interface ProfileAction {
  label: string;
  onClick: () => void;
  busy: boolean;
}

/** Card heading of the right column ("Your Profile", "Tips"): 24 px at every width. */
export function AsideTitle({ id, children }: { id: string; children: ReactNode }) {
  return (
    <h2 id={id} className="mb-[18px] text-qb-h3 font-medium tracking-normal text-(--color-qb-ink-heading)">
      {children}
    </h2>
  );
}

const IDENTITY_STYLES = {
  /** "Your Profile" on add-ads.html. */
  form: {
    name: 'text-qb-h5 text-qb-ink-title',
    badge: 'bg-qb-brand-soft text-qb-brand-on-soft',
    rows: 'gap-2.5',
    adsIcon: Check,
  },
  /** The seller card of preview.html. */
  preview: {
    name: 'text-[17px] text-qb-ink',
    badge: 'border border-qb-brand bg-qb-surface text-qb-brand',
    rows: 'gap-3',
    adsIcon: Tag,
  },
} as const;

/** Avatar, name, seller badge, ads count and member date, as on the add-ads and preview cards. */
export function SellerIdentity({ seller, variant = 'form' }: { seller: SellerSummary; variant?: keyof typeof IDENTITY_STYLES }) {
  const locale = getLocale();
  const styles = IDENTITY_STYLES[variant];
  return (
    <>
      {/* The seller badge moves under the name when a narrow card can't hold both. */}
      <div className="flex flex-wrap items-center justify-between gap-x-2 gap-y-2">
        <div className="flex min-w-0 flex-[1_1_180px] items-center gap-3">
          <Avatar name={seller.name} src={seller.avatarUrl} tone="brand" className="size-11 text-qb-body" />
          <NameWithMark name={seller.name} verified={seller.verified} className={cn('min-w-0 font-semibold break-words', styles.name)} />
        </div>
        <span className={cn('shrink-0 rounded-qb-sm px-3 py-[5px] font-qb-label text-qb-tiny font-medium', styles.badge)}>
          {t(`post_ad.profile.${seller.accountType}`)}
        </span>
      </div>
      <ul className="mt-[18px] flex flex-col gap-3.5 border-t border-qb-line pt-[18px] text-qb-body-sm text-qb-ink-secondary">
        <li className={cn('flex items-center', styles.rows)}>
          <Icon icon={styles.adsIcon} size="sm" className="size-[18px] text-qb-ink-subtle" />
          {seller.adsCount === undefined ? (
            <span className="h-4 w-14 animate-pulse rounded-qb-xs bg-qb-fill motion-reduce:animate-none" />
          ) : (
            tPlural('post_ad.profile.ads', seller.adsCount, locale)
          )}
        </li>
        <li className={cn('flex items-center', styles.rows)}>
          <Icon icon={CalendarDays} size="sm" className="size-[18px] text-qb-ink-subtle" />
          {t('post_ad.profile.member_since', { date: formatDate(seller.memberSince, locale) })}
        </li>
      </ul>
    </>
  );
}

/**
 * The name with the filled blue tick right after it, as on add-ads.html. The
 * tick stays on the line of the last word, so a long name wraps its words
 * instead of leaving the tick alone on a line.
 */
function NameWithMark({ name, verified, className }: { name: string; verified: boolean; className: string }) {
  if (!verified) return <span className={className}>{name}</span>;
  const lastSpace = name.lastIndexOf(' ');
  return (
    <span className={className}>
      {name.slice(0, lastSpace + 1)}
      <span className="whitespace-nowrap">
        {name.slice(lastSpace + 1)}
        <VerifiedMark className="ms-1.5 inline-block size-4 align-[-2px]" />
      </span>
    </span>
  );
}

/** "Your Profile" with the form's actions (Add Ads, Save Draft, Preview), side by side on phones. */
export function ProfileCard({
  seller,
  primary,
  secondary,
  preview,
  disabled,
}: {
  seller: SellerSummary;
  primary: ProfileAction;
  secondary?: ProfileAction;
  preview: ProfileAction;
  disabled: boolean;
}) {
  return (
    <section aria-labelledby="post-ad-profile" className={cardVariants()}>
      <AsideTitle id="post-ad-profile">{t('post_ad.profile.title')}</AsideTitle>
      <SellerIdentity seller={seller} />
      <div className="mt-[22px] flex flex-col gap-3 max-qb-tablet:flex-row">
        <ActionButton action={primary} disabled={disabled} variant="primary" className="h-[46px] text-qb-body" />
        {secondary ? (
          <ActionButton action={secondary} disabled={disabled} variant="secondary" className="h-[46px] text-qb-caption font-medium" />
        ) : null}
        <ActionButton
          action={preview}
          disabled={disabled}
          variant="ghost"
          className="h-7 text-qb-caption text-qb-brand hover:bg-transparent hover:underline max-qb-tablet:h-[42px]"
        />
      </div>
    </section>
  );
}

function ActionButton({
  action,
  disabled,
  variant,
  className,
}: {
  action: ProfileAction;
  disabled: boolean;
  variant: 'primary' | 'secondary' | 'ghost';
  className: string;
}) {
  return (
    <Button
      variant={variant}
      fullWidth
      disabled={disabled}
      aria-busy={action.busy || undefined}
      onClick={action.onClick}
      className={cn(
        'px-3.5 max-qb-tablet:h-[42px] max-qb-tablet:min-w-0 max-qb-tablet:flex-1 max-qb-tablet:px-1.5 max-qb-tablet:text-qb-caption',
        className,
      )}
    >
      {action.busy ? <Icon icon={LoaderCircle} size="sm" className="animate-spin motion-reduce:animate-none" /> : null}
      {action.label}
    </Button>
  );
}
