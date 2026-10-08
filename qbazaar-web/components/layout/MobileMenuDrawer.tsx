'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import {
  Bell,
  ChartColumn,
  ChevronRight,
  Heart,
  House,
  LayoutGrid,
  LogOut,
  MessageCircle,
  Plus,
  Search,
  Settings,
  Tag,
  Wallet,
  type LucideIcon,
} from 'lucide-react';

import { buttonVariants } from '@/components/design-system/Button';
import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { Sheet } from '@/components/design-system/Modal';
import { useSignOut } from '@/components/account/useSignOut';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

import { SiteLogo } from './SiteLogo';

interface NavItem {
  href: string;
  label: string;
  icon: LucideIcon;
}

/**
 * Guests get the browsing rows (741:46578); members also get their own pages
 * (648:47458), where the reference's "Sales Overview" is the orders page.
 */
function navItems(signedIn: boolean): NavItem[] {
  const browsing: NavItem[] = [
    { href: '/', label: t('layout.menu.home', 'الرئيسية'), icon: House },
    { href: '/categories', label: t('layout.menu.categories', 'الأقسام'), icon: LayoutGrid },
    { href: '/account/favorites', label: t('account.nav.favorites', 'المحفوظات'), icon: Heart },
    { href: '/account/messages', label: t('account.nav.messages', 'الرسائل'), icon: MessageCircle },
  ];
  const savedSearches: NavItem = {
    href: '/account/saved-searches',
    label: t('account.nav.saved_searches', 'عمليات البحث المحفوظة'),
    icon: Search,
  };
  if (!signedIn) return [...browsing, savedSearches];
  return [
    ...browsing,
    { href: '/account/notifications', label: t('account.nav.notifications', 'الإشعارات'), icon: Bell },
    savedSearches,
    { href: '/account/ads', label: t('layout.menu.my_ads', 'إعلاناتي'), icon: Tag },
    { href: '/account/orders', label: t('account.nav.orders', 'طلباتي'), icon: ChartColumn },
    { href: '/account', label: t('layout.menu.account', 'إعدادات الحساب'), icon: Settings },
    { href: '/account/wallet', label: t('account.nav.wallet', 'المحفظة'), icon: Wallet },
  ];
}

const row = 'flex w-full items-center gap-3.5 rounded-qb-lg px-3.5 py-[13px] text-qb-body-sm font-medium';
/** The reference's drawer buttons keep a 16 px icon, where the large button size draws 20. */
const footerButton = 'h-auto py-3.5 text-qb-body-sm [&_svg]:size-4';

interface MobileMenuDrawerProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  signedIn: boolean;
}

/** The slide-in navigation drawer of phones and small tablets (mobilemenu.js of the reference). */
export function MobileMenuDrawer({ open, onOpenChange, signedIn }: MobileMenuDrawerProps) {
  const pathname = usePathname() ?? '/';
  const close = () => onOpenChange(false);

  return (
    <Sheet
      side="start"
      open={open}
      onOpenChange={onOpenChange}
      title={t('layout.menu.title', 'القائمة')}
      heading={<SiteLogo width={80} height={30} />}
    >
      <nav aria-label={t('layout.menu.title', 'القائمة')} className="flex flex-1 flex-col">
        <p aria-hidden="true" className="px-5 pt-3.5 pb-1.5 text-qb-micro font-semibold text-qb-ink-subtle uppercase ltr:tracking-[0.08em]">
          {t('layout.menu.title', 'القائمة')}
        </p>
        <ul className="flex-1 px-3 pt-1.5 pb-3">
          {navItems(signedIn).map((item) => (
            <li key={item.href} className="mb-0.5">
              <MenuLink item={item} active={pathname === item.href} onNavigate={close} />
            </li>
          ))}
          {signedIn ? (
            <li>
              <SignOutRow />
            </li>
          ) : null}
        </ul>
        <div className="flex flex-col gap-3 border-t border-qb-line px-5 pt-3.5 pb-[22px]">
          {signedIn ? (
            <Link href="/post-ad" onClick={close} className={cn(buttonVariants({ size: 'lg', fullWidth: true }), footerButton)}>
              <Icon icon={Plus} strokeWidth={2.2} />
              {t('layout.header.add_ad', 'أضف إعلاناً')}
            </Link>
          ) : (
            <>
              <Link href="/register" onClick={close} className={cn(buttonVariants({ size: 'lg', fullWidth: true }), footerButton)}>
                {t('layout.header.sign_up', 'إنشاء حساب')}
              </Link>
              <Link
                href="/login"
                onClick={close}
                className={cn(buttonVariants({ variant: 'secondary', size: 'lg', fullWidth: true }), footerButton)}
              >
                {t('layout.header.login', 'تسجيل الدخول')}
              </Link>
            </>
          )}
        </div>
      </nav>
    </Sheet>
  );
}

function MenuLink({ item, active, onNavigate }: { item: NavItem; active: boolean; onNavigate: () => void }) {
  return (
    <Link
      href={item.href}
      onClick={onNavigate}
      aria-current={active ? 'page' : undefined}
      className={cn(row, focusRing, active ? 'bg-qb-brand text-qb-on-brand' : 'text-qb-ink-title hover:bg-qb-hover')}
    >
      <Icon icon={item.icon} className="size-[21px]" />
      <span className="flex-1">{item.label}</span>
      <RowChevron className={active ? 'text-qb-on-brand/80' : undefined} />
    </Link>
  );
}

function RowChevron({ className }: { className?: string }) {
  return <Icon icon={ChevronRight} size="sm" flipInRtl className={cn('text-qb-icon-muted', className)} />;
}

function SignOutRow() {
  const { signOut, pending } = useSignOut();
  return (
    <button type="button" onClick={signOut} disabled={pending} className={cn(row, focusRing, 'cursor-pointer text-qb-brand hover:bg-qb-hover')}>
      <Icon icon={LogOut} flipInRtl className="size-[21px]" />
      <span className="flex-1 text-start">{t('account.nav.sign_out', 'تسجيل الخروج')}</span>
      <RowChevron />
    </button>
  );
}
