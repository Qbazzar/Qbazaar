'use client';

/**
 * Global site header — QBFront port.
 *
 * Structure mirrors QBFront/index.html `header.site-header`:
 *   logo  · spacer  · "Add Ads" CTA  · icon buttons (saved · notifs · messages)
 *         · avatar  · theme toggle  · mobile burger
 *
 * Behaviour is identical to before: gated by `SiteHeaderGate` (hides on auth
 * pages + post-ad wizard), wires the user-channel subscription, and lights
 * up the unread badges via the live queries. Inline SVGs match QBFront —
 * lucide-react is avoided in this file so the marks stay 1:1 with the
 * prototype.
 */
import { useState } from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { Logo } from '@/components/ui/logo';
import { ThemeToggle } from '@/components/theme-toggle';
import { LocaleSwitcher } from '@/components/i18n/LocaleSwitcher';
import { cn } from '@/lib/utils';
import { t } from '@/lib/i18n/messages';
import { useAuth } from '@/hooks/useAuth';
import { useUserChannel } from '@/lib/echo/useUserChannel';
import { useUnreadCountQuery } from '@/lib/queries/messaging';
import { useUnreadNotificationsCountQuery } from '@/lib/queries/notifications';

export function SiteHeader() {
  const pathname = usePathname() ?? '/';
  const { isAuthenticated, isHydrated, user } = useAuth();
  const [mobileOpen, setMobileOpen] = useState(false);

  useUserChannel(isAuthenticated ? user?.id : null);

  // Badges are no-ops when signed out (the hooks short-circuit themselves).
  const { data: msgCount } = useUnreadCountQuery();
  const { data: notifCount } = useUnreadNotificationsCountQuery();

  const messages = isHydrated && isAuthenticated ? msgCount?.total ?? 0 : 0;
  const notifications = isHydrated && isAuthenticated ? notifCount?.total ?? 0 : 0;

  const initials = userInitials(user?.full_name ?? user?.email ?? '');
  const navItems = [
    { href: '/ads', label: t('ads.list.title', 'تصفّح') },
    { href: '/account/favorites', label: t('account.nav.favorites', 'المحفوظات') },
    { href: '/account/messages', label: t('account.nav.messages', 'الرسائل') },
    { href: '/account/notifications', label: t('account.nav.notifications', 'الإشعارات') },
    { href: '/account', label: t('account.nav.title', 'حسابي') },
    { href: '/help', label: t('footer.help', 'المساعدة') },
  ];

  return (
    <header className="site-header">
      <div className="container site-header__inner">
        <Link
          href="/"
          className="logo"
          aria-label={t('brand.name', 'QBazaar')}
        >
          <Logo />
        </Link>

        <div className="site-header__spacer" />

        {/* Desktop actions */}
        <div className="site-header__actions desktop-only">
          <Link href="/post-ad" className="btn btn--primary btn--pill">
            <svg
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="2.2"
              strokeLinecap="round"
            >
              <path d="M12 5v14M5 12h14" />
            </svg>
            {t('home.hero.cta_post', 'انشر إعلانك')}
          </Link>

          <Link
            href="/account/favorites"
            className="icon-btn"
            aria-label={t('account.nav.favorites', 'المحفوظات')}
          >
            <SaveIcon />
          </Link>

          <Link
            href="/account/notifications"
            className="icon-btn"
            aria-label={t('account.nav.notifications', 'الإشعارات')}
          >
            <BellIcon />
            {notifications > 0 ? (
              <span className="icon-btn__badge">
                {notifications > 99 ? '99+' : notifications}
              </span>
            ) : null}
          </Link>

          <Link
            href="/account/messages"
            className="icon-btn"
            aria-label={t('account.nav.messages', 'الرسائل')}
          >
            <ChatIcon />
            {messages > 0 ? (
              <span className="icon-btn__badge">
                {messages > 99 ? '99+' : messages}
              </span>
            ) : null}
          </Link>

          <LocaleSwitcher className="icon-btn" />

          <ThemeToggle />

          {isHydrated && isAuthenticated ? (
            <Link href="/account" className="avatar-link" aria-label={t('account.nav.title', 'حسابي')}>
              {initials || 'Q'}
            </Link>
          ) : (
            <Link href="/login" className="btn btn--ghost btn--sm btn--pill">
              {t('auth.tabs.login', 'تسجيل الدخول')}
            </Link>
          )}
        </div>

        {/* Mobile shortcut bar */}
        <div className="site-header__mobile">
          <Link href="/post-ad" className="btn btn--primary btn--sm btn--pill">
            + {t('home.hero.cta_post_short', 'انشر')}
          </Link>
          <LocaleSwitcher className="btn-mobile-toggle" />
          <button
            type="button"
            className="btn-mobile-toggle"
            aria-expanded={mobileOpen}
            aria-label={t('account.nav.title', 'القائمة')}
            onClick={() => setMobileOpen((v) => !v)}
          >
            <svg
              width="22"
              height="22"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="1.8"
              strokeLinecap="round"
            >
              <path d="M3 6h18M3 12h18M3 18h18" />
            </svg>
          </button>
        </div>
      </div>

      <nav className={cn('mobile-nav', mobileOpen && 'is-open')}>
        {navItems.map((item) => (
          <Link
            key={item.href}
            href={item.href}
            onClick={() => setMobileOpen(false)}
          >
            {item.label}
          </Link>
        ))}
      </nav>
    </header>
  );
}

function SaveIcon() {
  return (
    <svg
      width="18"
      height="18"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.6"
      strokeLinecap="round"
      strokeLinejoin="round"
    >
      <path d="M12 20s-7-4.5-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.5-7 10-7 10z" />
    </svg>
  );
}

function BellIcon() {
  return (
    <svg
      width="18"
      height="18"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.6"
      strokeLinecap="round"
      strokeLinejoin="round"
    >
      <path d="M6 16V10a6 6 0 1 1 12 0v6l2 2H4z" />
      <path d="M10 20a2 2 0 0 0 4 0" />
    </svg>
  );
}

function ChatIcon() {
  return (
    <svg
      width="18"
      height="18"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.6"
      strokeLinecap="round"
      strokeLinejoin="round"
    >
      <path d="M21 12a8 8 0 0 1-12 7l-5 1 1-5A8 8 0 1 1 21 12z" />
    </svg>
  );
}

function userInitials(input: string): string {
  const trimmed = input.trim();
  if (!trimmed) return '';
  if (trimmed.includes('@')) return trimmed[0]?.toUpperCase() ?? '';
  return trimmed
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('');
}

/**
 * Wrapper that hides the header on routes with their own chrome (auth pages
 * and the post-ad wizard).
 */
const HIDE_HEADER_PREFIXES = [
  '/login',
  '/register',
  '/forgot-password',
  '/reset-password',
  '/verify-otp',
  '/post-ad',
];

export function SiteHeaderGate() {
  const pathname = usePathname() ?? '/';
  if (HIDE_HEADER_PREFIXES.some((prefix) => pathname.startsWith(prefix))) {
    return null;
  }
  return <SiteHeader />;
}
