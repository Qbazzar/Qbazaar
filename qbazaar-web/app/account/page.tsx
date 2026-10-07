'use client';

/**
 * FE-2.1 — Account hub.
 *
 * Phones get the settings hub of 613:28184 (profile header + one row per
 * section). From the tablet layout up the settings sidebar is on screen, so
 * the hub shows the profile card, the `GET /account/summary` counters as
 * sales-overview style tiles (502:21437) and shortcuts.
 */
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import {
  Bell,
  Bookmark,
  ChevronRight,
  Clock,
  FilePen,
  Heart,
  LifeBuoy,
  LogOut,
  MapPinPlus,
  Megaphone,
  MessageCircle,
  type LucideIcon,
} from 'lucide-react';

import { buttonVariants } from '@/components/design-system/Button';
import { focusRing } from '@/components/design-system/focus-ring';
import { Badge } from '@/components/design-system/Badge';
import { Icon } from '@/components/design-system/Icon';
import { NamedAvatar } from '@/components/account/NamedAvatar';
import { AccountTypeBadge, ProfileSummary } from '@/components/account/ProfileSummary';
import { ACTIVITY_NAV, SETTINGS_NAV, type AccountNavItem } from '@/components/account/account-nav';
import { formatCount } from '@/components/account/format';
import { useSignOut } from '@/components/account/useSignOut';
import { useAuth } from '@/hooks/useAuth';
import { getAccountSummary } from '@/lib/api/account';
import type { AccountSummary, User } from '@/lib/api/types';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

interface StatTile {
  href: string;
  labelKey: string;
  icon: LucideIcon;
  value: (summary: AccountSummary) => number;
}

const STAT_TILES: readonly StatTile[] = [
  { href: '/account/ads', labelKey: 'account.dashboard.stats.my_ads', icon: Megaphone, value: (s) => s.my_ads },
  { href: '/account/ads', labelKey: 'account.dashboard.stats.drafts', icon: FilePen, value: (s) => s.drafts },
  {
    href: '/account/messages',
    labelKey: 'account.dashboard.stats.unread_messages',
    icon: MessageCircle,
    value: (s) => s.unread_messages,
  },
  {
    href: '/account/notifications',
    labelKey: 'account.dashboard.stats.unread_notifications',
    icon: Bell,
    value: (s) => s.unread_notifications,
  },
  { href: '/account/favorites', labelKey: 'account.dashboard.stats.favorites', icon: Heart, value: (s) => s.favorites },
  {
    href: '/account/saved-searches',
    labelKey: 'account.dashboard.stats.saved_searches',
    icon: Bookmark,
    value: (s) => s.saved_searches,
  },
];

/** Counter shown next to an activity row on the phone hub. */
const ACTIVITY_COUNTS: Partial<Record<string, (summary: AccountSummary) => number>> = {
  '/account/ads': (s) => s.my_ads,
  '/account/messages': (s) => s.unread_messages,
  '/account/notifications': (s) => s.unread_notifications,
  '/account/favorites': (s) => s.favorites,
  '/account/saved-searches': (s) => s.saved_searches,
};

export default function AccountHubPage() {
  const { user } = useAuth();
  const { data: summary, isFetching } = useQuery({
    queryKey: ['account', 'summary'],
    queryFn: getAccountSummary,
    // Layout already gated by `useRequireAuth`, but belt-and-braces:
    enabled: Boolean(user),
  });

  if (!user) return null;

  return (
    <section className="font-qb">
      <h1 className="sr-only qb-tablet:not-sr-only qb-tablet:text-qb-h5 qb-tablet:leading-none qb-tablet:font-semibold qb-tablet:text-qb-ink-title">
        {t('account.nav.overview')}
      </h1>
      <p className="hidden text-qb-caption font-medium text-qb-ink-subtle qb-tablet:mt-[18px] qb-tablet:block">
        {t('account.dashboard.subtitle')}
      </p>

      <PhoneHub user={user} summary={summary} />

      <div className="hidden qb-tablet:mt-[47px] qb-tablet:block">
        <ProfileSummary
          name={user.full_name}
          avatarUrl={user.avatar_url}
          accountType={user.account_type}
          joinedAt={user.created_at}
          subtitle={<span dir="ltr">{user.email}</span>}
        />
        <ul aria-busy={isFetching} className="mt-6 [display:grid] grid-cols-2 gap-4 qb-desktop:grid-cols-3">
          {STAT_TILES.map((tile) => (
            <li key={tile.labelKey}>
              <StatTileLink tile={tile} value={summary ? tile.value(summary) : null} />
            </li>
          ))}
        </ul>
        <div className="mt-8 flex flex-wrap gap-3">
          <Link href="/post-ad" className={buttonVariants({ size: 'sm' })}>
            <MapPinPlus aria-hidden="true" />
            {t('account.dashboard.quick_actions.post_ad')}
          </Link>
          <Link href="/account/recently-viewed" className={buttonVariants({ variant: 'outline', size: 'sm' })}>
            <Clock aria-hidden="true" />
            {t('account.nav.recently_viewed')}
          </Link>
          <Link href="/account/support" className={buttonVariants({ variant: 'outline', size: 'sm' })}>
            <LifeBuoy aria-hidden="true" />
            {t('account.nav.support')}
          </Link>
        </div>
      </div>
    </section>
  );
}

function StatTileLink({ tile, value }: { tile: StatTile; value: number | null }) {
  return (
    <Link
      href={tile.href}
      className={cn(
        'flex h-full flex-col rounded-qb-2xl border border-qb-line bg-qb-surface p-6 shadow-qb-card transition-shadow hover:shadow-qb-hover',
        focusRing,
      )}
    >
      <span aria-hidden="true" className="flex size-[47px] items-center justify-center rounded-qb-lg bg-qb-brand-soft text-qb-brand">
        <Icon icon={tile.icon} size="lg" />
      </span>
      <span className="mt-3 text-qb-body text-qb-ink-subtle">{t(tile.labelKey)}</span>
      <span className="mt-3 text-qb-h2 leading-none font-semibold text-qb-ink-title">
        {value === null ? '—' : formatCount(value)}
      </span>
    </Link>
  );
}

/** Settings hub of 613:28184: profile header, then one row per section. */
function PhoneHub({ user, summary }: { user: User; summary?: AccountSummary }) {
  const { signOut, pending } = useSignOut();

  return (
    <div className="qb-tablet:hidden">
      <div className="flex items-center gap-4 px-2 pt-4">
        <NamedAvatar name={user.full_name} src={user.avatar_url} size="lg" tone="neutral" className="size-[63px] bg-qb-fill" />
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-2">
            <p className="truncate text-qb-h5 font-semibold text-qb-ink">{user.full_name}</p>
            <AccountTypeBadge accountType={user.account_type} />
          </div>
          <p className="mt-1.5 truncate text-qb-caption font-medium text-qb-ink-subtle">
            <span dir="ltr">{user.email}</span>
          </p>
        </div>
      </div>

      <HubGroup items={SETTINGS_NAV} className="mt-4" />
      <h2 className="mt-6 px-2 text-qb-body font-semibold text-qb-ink-secondary">{t('account.nav.activity')}</h2>
      <HubGroup items={ACTIVITY_NAV} summary={summary} className="mt-1" />

      <button
        type="button"
        onClick={signOut}
        disabled={pending}
        className={cn(
          'mt-4 flex h-14 w-full items-center gap-3 rounded-qb-xl px-2 text-qb-body font-medium text-qb-danger hover:bg-qb-danger-soft disabled:opacity-60',
          focusRing,
        )}
      >
        <Icon icon={LogOut} size="lg" flipInRtl />
        {t('account.nav.sign_out')}
      </button>
    </div>
  );
}

function HubGroup({
  items,
  summary,
  className,
}: {
  items: readonly AccountNavItem[];
  summary?: AccountSummary;
  className?: string;
}) {
  return (
    <ul className={cn('rounded-qb-xl bg-qb-surface', className)}>
      {items.map((item) => {
        const count = summary ? ACTIVITY_COUNTS[item.href]?.(summary) : undefined;
        return (
          <li key={item.href}>
            <Link
              href={item.href}
              className={cn(
                'flex h-14 items-center gap-3 rounded-qb-md px-2 text-qb-body font-medium hover:bg-qb-hover',
                item.danger ? 'text-qb-danger' : 'text-qb-ink',
                focusRing,
              )}
            >
              <Icon icon={item.icon} size="lg" className={item.danger ? 'text-qb-danger' : 'text-qb-ink-secondary'} />
              <span className="min-w-0 flex-1 truncate">{t(item.labelKey)}</span>
              {count ? (
                <Badge tone="brand" size="sm" className="rounded-qb-pill font-semibold">
                  {count > 99 ? `${formatCount(99)}+` : formatCount(count)}
                </Badge>
              ) : null}
              {item.danger ? null : <Icon icon={ChevronRight} flipInRtl className="text-qb-ink-subtle" />}
            </Link>
          </li>
        );
      })}
    </ul>
  );
}
