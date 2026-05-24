'use client';

/**
 * Global site header (Sprint 6).
 *
 * Three rows of affordances:
 *  1. Brand wordmark + primary nav links (home, categories, ads, post-ad).
 *  2. Search bar — full-width on desktop, icon button on mobile.
 *  3. Account avatar / sign-in link.
 *
 * The header is path-aware: it self-hides on the auth split-layout and the
 * post-ad wizard (both have their own chrome). Rendering is gated by the
 * wrapper component `SiteHeaderGate` so this file only worries about layout.
 */
import { Suspense, useState } from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import {
  SearchIcon,
  MenuIcon,
  PlusIcon,
  UserCircle2Icon,
  XIcon,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { SearchBar } from '@/components/search/SearchBar';
import { MessagesBadge } from '@/components/messaging/MessagesBadge';
import { cn } from '@/lib/utils';
import { t } from '@/lib/i18n/messages';
import { useAuth } from '@/hooks/useAuth';
import { useUserChannel } from '@/lib/echo/useUserChannel';

interface NavLink {
  href: string;
  labelKey: string;
}

const NAV_LINKS: NavLink[] = [
  { href: '/', labelKey: 'home.breadcrumb' },
  { href: '/categories', labelKey: 'categories.all' },
  { href: '/ads', labelKey: 'ads.list.title' },
];

export function SiteHeader() {
  const pathname = usePathname() ?? '/';
  const { isAuthenticated, isHydrated, user } = useAuth();
  const [mobileSearchOpen, setMobileSearchOpen] = useState(false);

  // Global user-channel subscription keeps the badge + inbox previews live
  // for the whole authenticated app — one subscription, one set of cache
  // invalidations.
  useUserChannel(isAuthenticated ? user?.id : null);

  const isActive = (href: string) => {
    if (href === '/') return pathname === '/';
    return pathname === href || pathname.startsWith(`${href}/`);
  };

  return (
    <header className="border-ink-200 bg-card/95 supports-[backdrop-filter]:bg-card/70 sticky top-0 z-30 border-b backdrop-blur">
      <div className="mx-auto flex h-16 w-full max-w-6xl items-center gap-3 px-4 sm:px-6">
        {/* Brand */}
        <Link
          href="/"
          className="text-coral font-display flex shrink-0 items-center gap-2 text-xl"
        >
          <span>{t('brand.name', 'QBazaar')}</span>
        </Link>

        {/* Desktop nav */}
        <nav className="hidden items-center gap-1 lg:flex">
          {NAV_LINKS.map((link) => (
            <Link
              key={link.href}
              href={link.href}
              aria-current={isActive(link.href) ? 'page' : undefined}
              className={cn(
                'rounded-full px-3 py-1.5 text-sm font-medium transition-colors',
                isActive(link.href)
                  ? 'bg-coral/10 text-coral'
                  : 'text-ink-700 hover:bg-cream-200',
              )}
            >
              {t(link.labelKey)}
            </Link>
          ))}
        </nav>

        {/* Desktop search */}
        <div className="hidden flex-1 md:block">
          <Suspense fallback={<SearchBarSkeleton />}>
            <SearchBar className="mx-auto max-w-xl" compact />
          </Suspense>
        </div>

        <div className="ms-auto flex shrink-0 items-center gap-1.5 md:ms-2">
          {/* Mobile search trigger */}
          <Button
            type="button"
            variant="ghost"
            size="icon"
            aria-label={t('search.open', 'فتح البحث')}
            className="md:hidden"
            onClick={() => setMobileSearchOpen(true)}
          >
            <SearchIcon className="size-5" aria-hidden />
          </Button>

          {/* Post-ad CTA */}
          <Button
            asChild
            size="default"
            className="bg-coral hover:bg-coral/90 hidden rounded-full text-white sm:inline-flex"
          >
            <Link href="/post-ad">
              <PlusIcon className="size-3.5" aria-hidden />
              {t('home.hero.cta_post', 'انشر إعلانك')}
            </Link>
          </Button>

          {/* Messages badge — auto-hides when signed out or count is 0 */}
          <MessagesBadge />

          {/* Account */}
          {isHydrated && isAuthenticated ? (
            <Button
              asChild
              variant="ghost"
              size="icon"
              aria-label={t('account.nav.title', 'حسابي')}
            >
              <Link href="/account">
                <UserCircle2Icon className="size-5" aria-hidden />
              </Link>
            </Button>
          ) : (
            <Button
              asChild
              variant="outline"
              size="default"
              className="rounded-full"
            >
              <Link href="/login">{t('auth.tabs.login', 'تسجيل الدخول')}</Link>
            </Button>
          )}

          {/* Mobile menu (kept tiny in Wave A — just links to existing pages) */}
          <details className="relative lg:hidden">
            <summary
              className="hover:bg-cream-200 grid size-8 cursor-pointer list-none place-items-center rounded-full [&::-webkit-details-marker]:hidden"
              aria-label={t('account.nav.title', 'القائمة')}
            >
              <MenuIcon className="size-5" aria-hidden />
            </summary>
            <ul className="border-ink-200 bg-card absolute end-0 top-full z-30 mt-2 w-52 rounded-xl border p-1 shadow-lg">
              {NAV_LINKS.map((link) => (
                <li key={link.href}>
                  <Link
                    href={link.href}
                    className="text-ink-700 hover:bg-cream-200 block rounded-md px-3 py-2 text-sm"
                  >
                    {t(link.labelKey)}
                  </Link>
                </li>
              ))}
              <li>
                <Link
                  href="/post-ad"
                  className="text-coral hover:bg-coral/10 block rounded-md px-3 py-2 text-sm font-medium"
                >
                  {t('home.hero.cta_post', 'انشر إعلانك')}
                </Link>
              </li>
            </ul>
          </details>
        </div>
      </div>

      {/* Mobile search overlay */}
      {mobileSearchOpen ? (
        <div className="border-ink-200 bg-card border-t px-4 py-3 md:hidden">
          <div className="flex items-center gap-2">
            <Suspense fallback={<SearchBarSkeleton />}>
              <SearchBar
                className="flex-1"
                compact
                autoFocus
                onAfterSubmit={() => setMobileSearchOpen(false)}
              />
            </Suspense>
            <Button
              type="button"
              variant="ghost"
              size="icon"
              aria-label={t('search.close', 'إغلاق')}
              onClick={() => setMobileSearchOpen(false)}
            >
              <XIcon className="size-5" aria-hidden />
            </Button>
          </div>
        </div>
      ) : null}
    </header>
  );
}

function SearchBarSkeleton() {
  return (
    <div className="bg-cream-200 mx-auto h-10 w-full max-w-xl animate-pulse rounded-full" />
  );
}

/**
 * Wrapper that hides the header on routes with their own chrome (auth pages
 * and the post-ad wizard). Kept in the same file so the routing rules live
 * next to the component they affect.
 */
const HIDE_HEADER_PREFIXES = ['/login', '/register', '/forgot-password', '/reset-password', '/verify-otp', '/post-ad'];

export function SiteHeaderGate() {
  const pathname = usePathname() ?? '/';
  if (HIDE_HEADER_PREFIXES.some((prefix) => pathname.startsWith(prefix))) {
    return null;
  }
  return <SiteHeader />;
}
