'use client';

/**
 * Global site header on the new design (728:44663 desktop, 648:47458 menu).
 * Hidden on routes with their own chrome (auth pages, the post-ad wizard).
 * Subscribes the signed-in user's channel and shows the unread markers from
 * the live queries.
 */
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { Bell, Globe, Heart, MapPinPlus, MessageCircle, type LucideIcon } from 'lucide-react';

import { Avatar } from '@/components/design-system/Avatar';
import { buttonVariants } from '@/components/design-system/Button';
import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { LocaleSwitcher } from '@/components/i18n/LocaleSwitcher';
import { useAuth } from '@/hooks/useAuth';
import { useUserChannel } from '@/lib/echo/useUserChannel';
import { t } from '@/lib/i18n/messages';
import { useUnreadCountQuery } from '@/lib/queries/messaging';
import { useUnreadNotificationsCountQuery } from '@/lib/queries/notifications';
import { cn } from '@/lib/utils';

import { MobileMenu, mobileIconButton } from './MobileMenu';
import { hasOwnChrome } from './own-chrome';
import { SiteLogo } from './SiteLogo';

export function SiteHeaderGate() {
  const pathname = usePathname() ?? '/';
  return hasOwnChrome(pathname) ? null : <SiteHeader />;
}

export function SiteHeader() {
  const { isAuthenticated, isHydrated, user } = useAuth();
  useUserChannel(isAuthenticated ? user?.id : null);

  // Both hooks do nothing while signed out.
  const { data: msgCount } = useUnreadCountQuery();
  const { data: notifCount } = useUnreadNotificationsCountQuery();
  const signedIn = isHydrated && isAuthenticated;
  const unread = {
    messages: signedIn ? (msgCount?.total ?? 0) : 0,
    notifications: signedIn ? (notifCount?.total ?? 0) : 0,
  };

  return (
    <header className="sticky top-0 z-40 bg-qb-surface font-qb text-qb-ink shadow-qb-raised">
      <div className="mx-auto flex h-[88px] max-w-[1440px] items-center justify-between gap-4 px-qb-gutter">
        <Link href="/" aria-label={t('brand.name', 'QBazaar')} className={cn('shrink-0 rounded-qb-sm', focusRing)}>
          <SiteLogo width={139} height={52} eager />
        </Link>

        <div className="hidden items-center gap-[clamp(14px,1.6vw,20px)] min-[761px]:flex">
          <Link href="/post-ad" className={cn(buttonVariants({ size: 'sm' }), 'h-auto gap-[7px] py-2.5 text-qb-body-sm leading-[1.15]')}>
            <Icon icon={MapPinPlus} className="size-5" />
            {t('layout.header.add_ad', 'أضف إعلاناً')}
          </Link>
          <span aria-hidden="true" className="h-[30px] w-px bg-qb-line" />
          <LocaleSwitcher className={headerIcon}>
            <Icon icon={Globe} className="size-[23px]" />
          </LocaleSwitcher>
          <HeaderIconLink href="/account/favorites" icon={Heart} label={t('account.nav.favorites', 'المحفوظات')} />
          <HeaderIconLink
            href="/account/notifications"
            icon={Bell}
            label={t('account.nav.notifications', 'الإشعارات')}
            unread={unread.notifications}
          />
          <HeaderIconLink href="/account/messages" icon={MessageCircle} label={t('account.nav.messages', 'الرسائل')} unread={unread.messages} />
          {signedIn ? (
            <Link href="/account" aria-label={t('account.nav.title', 'حسابي')} className={cn('rounded-full', focusRing)}>
              <Avatar name={user?.full_name || user?.email || 'Q'} />
            </Link>
          ) : (
            <GuestButtons />
          )}
        </div>

        <div className="flex items-center gap-2 self-start pt-3.5 min-[761px]:hidden">
          <LocaleSwitcher className={mobileIconButton}>
            <Icon icon={Globe} />
          </LocaleSwitcher>
          <HeaderIconLink
            href="/account/notifications"
            icon={Bell}
            label={t('account.nav.notifications', 'الإشعارات')}
            unread={unread.notifications}
            variant="phone"
          />
          <MobileMenu signedIn={signedIn} />
        </div>
      </div>
    </header>
  );
}

const headerIcon = cn(
  'relative inline-flex size-[27px] cursor-pointer items-center justify-center rounded-qb-xs text-qb-ink-body transition-colors hover:text-qb-brand',
  focusRing,
);

/** The bare icons of the desktop bar and the bordered squares of the phone header. */
const ICON_LINK = {
  desktop: { link: headerIcon, icon: 'size-[23px]', dot: 'end-0 top-0 size-2 border-[1.5px] border-qb-surface' },
  phone: { link: mobileIconButton, icon: 'size-5', dot: 'end-3 top-[11px] size-[7px]' },
} as const;

interface HeaderIconLinkProps {
  href: string;
  icon: LucideIcon;
  label: string;
  unread?: number;
  variant?: keyof typeof ICON_LINK;
}

function HeaderIconLink({ href, icon, label, unread = 0, variant = 'desktop' }: HeaderIconLinkProps) {
  const style = ICON_LINK[variant];
  const name = unread > 0 ? t('layout.header.unread', { label, count: unread }, `${label} (${unread})`) : label;
  return (
    <Link href={href} aria-label={name} className={style.link}>
      <Icon icon={icon} className={style.icon} />
      {unread > 0 ? <span aria-hidden="true" className={cn('absolute rounded-full bg-qb-brand', style.dot)} /> : null}
    </Link>
  );
}

function GuestButtons() {
  return (
    <span className="ms-1 flex items-center gap-2.5">
      <Link href="/login" className={cn(buttonVariants({ variant: 'outline', size: 'sm' }), 'h-[43px]')}>
        {t('layout.header.login', 'تسجيل الدخول')}
      </Link>
      <Link href="/register" className={cn(buttonVariants({ size: 'sm' }), 'h-[41px]')}>
        {t('layout.header.sign_up', 'إنشاء حساب')}
      </Link>
    </span>
  );
}
