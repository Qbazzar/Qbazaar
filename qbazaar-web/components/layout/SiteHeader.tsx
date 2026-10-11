'use client';

/**
 * Global site header on the new design (728:44663 desktop, 648:47458 menu).
 * Hidden on routes with their own chrome (the auth pages).
 * Subscribes the signed-in user's channel and shows the unread markers from
 * the live queries.
 */
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { Bell, Globe, Heart, MapPinPlus, MessageCircle, type LucideIcon } from 'lucide-react';

import { buttonVariants } from '@/components/design-system/Button';
import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { siteFrame } from '@/components/design-system/site-frame';
import { LanguageMenu } from '@/components/i18n/LanguageMenu';
import { useAuth } from '@/hooks/useAuth';
import { useUserChannel } from '@/lib/echo/useUserChannel';
import { formatNumber } from '@/lib/i18n/format';
import { getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { useUnreadCountQuery } from '@/lib/queries/messaging';
import { useUnreadNotificationsCountQuery } from '@/lib/queries/notifications';
import { cn } from '@/lib/utils';

import { AccountMenu } from './AccountMenu';
import { MAIN_CONTENT_ID } from './main-content';
import { MobileMenu, mobileIconButton } from './MobileMenu';
import { hasOwnChrome } from './own-chrome';
import { SiteLogo } from './SiteLogo';

export function SiteHeaderGate() {
  const pathname = usePathname() ?? '/';
  if (hasOwnChrome(pathname)) return null;
  return (
    <>
      <a href={`#${MAIN_CONTENT_ID}`} className={skipLink}>
        {t('layout.skip_to_content', 'انتقل إلى المحتوى')}
      </a>
      <SiteHeader />
    </>
  );
}

/** Off screen until a keyboard user reaches it, then shown above the header. The shadow waits too, or it shows at the top edge. */
const skipLink = cn(
  'fixed start-4 top-3 z-50 -translate-y-[calc(100%+1rem)] rounded-qb-md bg-qb-surface px-4 py-3 font-qb text-qb-body font-medium text-qb-ink focus:translate-y-0 focus:shadow-qb-popover motion-safe:transition-transform',
  focusRing,
);

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
      <div className={cn(siteFrame, 'flex h-[88px] items-center justify-between gap-4')}>
        <Link href="/" aria-label={t('brand.name', 'QBazaar')} className={cn('shrink-0 rounded-qb-sm', focusRing)}>
          <SiteLogo width={139} height={52} eager />
        </Link>

        {/* Arabic labels are wider, so the gaps shrink sooner and the bar keeps its gutter at 761 px. */}
        <div className="hidden items-center gap-[clamp(14px,1.6vw,20px)] rtl:gap-[clamp(10px,1.4vw,20px)] min-[761px]:flex">
          <Link
            href="/post-ad"
            className={cn(buttonVariants({ size: 'sm' }), 'h-auto gap-[7px] py-2.5 text-qb-body-sm leading-[1.15] [&_svg]:size-5')}
          >
            <Icon icon={MapPinPlus} />
            {t('layout.header.add_ad', 'أضف إعلاناً')}
          </Link>
          <span aria-hidden="true" className="h-[30px] w-px bg-qb-line" />
          <LanguageMenu className={headerIcon}>
            <Icon icon={Globe} className="size-[23px]" />
          </LanguageMenu>
          <HeaderIconLink href="/account/favorites" icon={Heart} label={t('layout.menu.favorites', 'المفضلة')} />
          <HeaderIconLink
            href="/account/notifications"
            icon={Bell}
            label={t('account.nav.notifications', 'الإشعارات')}
            unread={unread.notifications}
          />
          <HeaderIconLink href="/account/messages" icon={MessageCircle} label={t('account.nav.messages', 'الرسائل')} unread={unread.messages} />
          {signedIn ? (
            <AccountMenu name={user?.full_name || user?.email || 'Q'} email={user?.email} />
          ) : (
            <GuestButtons />
          )}
        </div>

        <div className="relative flex items-center gap-2 self-start pt-3.5 min-[761px]:hidden">
          <LanguageMenu className={mobileIconButton} variant="phone">
            <Icon icon={Globe} />
          </LanguageMenu>
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
  const count = formatNumber(unread, getLocale());
  const name = unread > 0 ? t('layout.header.unread', { label, count }, `${label} (${count})`) : label;
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
