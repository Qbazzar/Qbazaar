'use client';

import type { ReactNode } from 'react';
import { BadgeCheck, CalendarDays, LoaderCircle, Tag } from 'lucide-react';

import { Avatar } from '@/components/design-system/Avatar';
import { Button } from '@/components/design-system/Button';
import { Icon } from '@/components/design-system/Icon';
import type { AccountType } from '@/lib/api/types';
import { getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { formatDate } from '@/lib/post-ad/format';
import { cn } from '@/lib/utils';

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

/** Card heading of the right column ("Your Profile", "Tips"). */
export function AsideTitle({ id, children }: { id: string; children: ReactNode }) {
  return (
    <h2 id={id} className="mb-[18px] text-qb-h5 font-medium tracking-normal text-qb-ink-body qb-tablet:text-qb-h3">
      {children}
    </h2>
  );
}

/** Avatar, name, seller badge, ads count and member date, as on the add-ads and preview cards. */
export function SellerIdentity({ seller }: { seller: SellerSummary }) {
  const locale = getLocale();
  return (
    <>
      {/* The badge moves under the name when a narrow card can't hold both. */}
      <div className="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-2">
        <div className="flex min-w-0 flex-[1_1_180px] items-center gap-3">
          <Avatar name={seller.name} src={seller.avatarUrl} tone="brand" className="size-11 text-qb-body" />
          <span className="min-w-0 text-qb-h5 font-semibold break-words text-qb-ink-title">
            {seller.name}
            {seller.verified ? (
              <Icon icon={BadgeCheck} size="sm" label={t('post_ad.profile.verified')} className="ms-1.5 inline-block align-[-2px] text-qb-info" />
            ) : null}
          </span>
        </div>
        <span className="shrink-0 rounded-qb-sm bg-qb-brand-soft px-3 py-[5px] font-qb-label text-qb-tiny font-medium text-qb-brand">
          {t(`post_ad.profile.${seller.accountType}`)}
        </span>
      </div>
      <ul className="mt-[18px] flex flex-col gap-3.5 border-t border-qb-line pt-[18px] text-qb-body-sm text-qb-ink-secondary">
        <li className="flex items-center gap-2.5">
          <Icon icon={Tag} size="sm" className="size-[18px] text-qb-ink-subtle" />
          {seller.adsCount === undefined ? (
            <span className="h-4 w-14 animate-pulse rounded-qb-xs bg-qb-fill motion-reduce:animate-none" />
          ) : (
            t('post_ad.profile.ads', { count: seller.adsCount })
          )}
        </li>
        <li className="flex items-center gap-2.5">
          <Icon icon={CalendarDays} size="sm" className="size-[18px] text-qb-ink-subtle" />
          {t('post_ad.profile.member_since', { date: formatDate(seller.memberSince, locale) })}
        </li>
      </ul>
    </>
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
    <section aria-labelledby="post-ad-profile" className="rounded-qb-xl border border-qb-line bg-qb-surface p-6 font-qb">
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
      className={cn('px-3.5 max-qb-tablet:h-[42px] max-qb-tablet:min-w-0 max-qb-tablet:flex-1 max-qb-tablet:px-1.5 max-qb-tablet:text-qb-caption', className)}
    >
      {action.busy ? <Icon icon={LoaderCircle} size="sm" className="animate-spin motion-reduce:animate-none" /> : null}
      {action.label}
    </Button>
  );
}
