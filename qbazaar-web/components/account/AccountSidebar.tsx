'use client';

/**
 * AccountSidebar — left rail on desktop, horizontal scroller on mobile.
 *
 * The 7 authenticated account pages live behind this nav. Active routes are
 * highlighted with the coral accent + a subtle pill background so the
 * relationship with the rest of the design system stays consistent.
 *
 * The sign-out button at the bottom calls the shared `useAuth().logout()`
 * helper, then pushes the user to `/login`.
 */
import { useTransition } from 'react';
import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { toast } from 'sonner';
import {
  BookmarkIcon,
  ClockIcon,
  DatabaseIcon,
  HeartIcon,
  HomeIcon,
  LogOutIcon,
  MessageSquareIcon,
  ShieldCheckIcon,
  KeyIcon,
  MonitorSmartphoneIcon,
  LockKeyholeIcon,
  UserIcon,
  UserXIcon,
} from 'lucide-react';

import { cn } from '@/lib/utils';
import { t } from '@/lib/i18n/messages';
import { useAuth } from '@/hooks/useAuth';

interface NavItem {
  href: string;
  labelKey: string;
  icon: React.ComponentType<{ className?: string }>;
}

const NAV_ITEMS: NavItem[] = [
  { href: '/account', labelKey: 'account.nav.dashboard', icon: HomeIcon },
  { href: '/account/profile', labelKey: 'account.nav.profile', icon: UserIcon },
  { href: '/account/security', labelKey: 'account.nav.security', icon: KeyIcon },
  {
    href: '/account/sessions',
    labelKey: 'account.nav.sessions',
    icon: MonitorSmartphoneIcon,
  },
  {
    href: '/account/verification',
    labelKey: 'account.nav.verification',
    icon: ShieldCheckIcon,
  },
  {
    href: '/account/privacy',
    labelKey: 'account.nav.privacy',
    icon: LockKeyholeIcon,
  },
  {
    href: '/account/data',
    labelKey: 'account.nav.data',
    icon: DatabaseIcon,
  },
  {
    href: '/account/messages',
    labelKey: 'account.nav.messages',
    icon: MessageSquareIcon,
  },
  {
    href: '/account/favorites',
    labelKey: 'account.nav.favorites',
    icon: HeartIcon,
  },
  {
    href: '/account/recently-viewed',
    labelKey: 'account.nav.recently_viewed',
    icon: ClockIcon,
  },
  {
    href: '/account/saved-searches',
    labelKey: 'account.nav.saved_searches',
    icon: BookmarkIcon,
  },
  {
    href: '/account/blocked-users',
    labelKey: 'account.nav.blocked_users',
    icon: UserXIcon,
  },
];

export function AccountSidebar() {
  const pathname = usePathname();
  const router = useRouter();
  const { logout } = useAuth();
  const [pendingLogout, startLogout] = useTransition();

  const isActive = (href: string) => {
    if (href === '/account') return pathname === '/account';
    return pathname === href || pathname.startsWith(`${href}/`);
  };

  const handleSignOut = () => {
    startLogout(async () => {
      try {
        await logout();
        toast.success(t('account.nav.sign_out_confirm'));
      } finally {
        router.replace('/login');
      }
    });
  };

  return (
    <nav
      aria-label={t('account.nav.title')}
      className="lg:bg-card lg:ring-foreground/10 lg:flex lg:h-full lg:flex-col lg:gap-4 lg:rounded-2xl lg:p-3 lg:ring-1"
    >
      <h2 className="font-display text-ink-900 hidden text-lg tracking-tight lg:block lg:px-3 lg:pt-2">
        {t('account.nav.title')}
      </h2>

      <ul
        className={cn(
          'flex gap-1 overflow-x-auto pb-1',
          'lg:flex-col lg:gap-0.5 lg:overflow-visible lg:pb-0',
        )}
      >
        {NAV_ITEMS.map((item) => {
          const active = isActive(item.href);
          const Icon = item.icon;
          return (
            <li key={item.href} className="shrink-0 lg:shrink">
              <Link
                href={item.href}
                aria-current={active ? 'page' : undefined}
                className={cn(
                  'flex items-center gap-2.5 rounded-full px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors',
                  'lg:rounded-lg lg:px-3 lg:py-2',
                  active
                    ? 'bg-coral/10 text-coral'
                    : 'text-ink-700 hover:bg-muted hover:text-ink-900',
                )}
              >
                <Icon
                  aria-hidden="true"
                  className={cn('size-4', active && 'text-coral')}
                />
                <span>{t(item.labelKey)}</span>
              </Link>
            </li>
          );
        })}
      </ul>

      <div className="hidden lg:mt-auto lg:block lg:pt-2">
        <button
          type="button"
          onClick={handleSignOut}
          disabled={pendingLogout}
          className={cn(
            'flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
            'text-destructive hover:bg-destructive/10 disabled:cursor-progress disabled:opacity-60',
          )}
        >
          <LogOutIcon className="size-4" aria-hidden="true" />
          <span>{t('account.nav.sign_out')}</span>
        </button>
      </div>
    </nav>
  );
}
