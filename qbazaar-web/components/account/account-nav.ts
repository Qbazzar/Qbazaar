import {
  Bell,
  Bookmark,
  Clock,
  Heart,
  LayoutGrid,
  LifeBuoy,
  Megaphone,
  MessageCircle,
  MonitorSmartphone,
  Rocket,
  Settings,
  ShieldCheck,
  ShieldHalf,
  ShoppingBag,
  Trash2,
  User,
  UserX,
  Wallet,
  type LucideIcon,
} from 'lucide-react';

export interface AccountNavItem {
  href: string;
  labelKey: string;
  icon: LucideIcon;
  /** Destructive sections are listed in the danger colour (the design's "Delete Account"). */
  danger?: boolean;
  /** Only the page itself sits in the settings shell; its sub-pages are full-width pages. */
  exact?: boolean;
}

export const ACCOUNT_HUB_PATH = '/account';

export const ACCOUNT_HUB_ITEM: AccountNavItem = {
  href: ACCOUNT_HUB_PATH,
  labelKey: 'account.nav.overview',
  icon: LayoutGrid,
};

/** The "Settings" sidebar of `account.html` (394:9270); on phones the hub lists the same rows. */
export const SETTINGS_NAV: readonly AccountNavItem[] = [
  { href: '/account/profile', labelKey: 'account.nav.profile_settings', icon: User },
  { href: '/account/wallet', labelKey: 'account.nav.wallet', icon: Wallet, exact: true },
  { href: '/account/security', labelKey: 'account.nav.account_settings', icon: Settings },
  { href: '/account/sessions', labelKey: 'account.nav.sessions', icon: MonitorSmartphone },
  { href: '/account/verification', labelKey: 'account.nav.verification', icon: ShieldCheck },
  { href: '/account/privacy', labelKey: 'account.nav.data_protection', icon: ShieldHalf },
  { href: '/account/blocked-users', labelKey: 'account.nav.blocked_users', icon: UserX },
  { href: '/account/data', labelKey: 'account.nav.data', icon: Trash2, danger: true },
];

/** Full-width account pages, reached from the header and from the hub. */
export const ACTIVITY_NAV: readonly AccountNavItem[] = [
  { href: '/account/ads', labelKey: 'account.nav.my_ads', icon: Megaphone },
  { href: '/account/orders', labelKey: 'account.nav.orders', icon: ShoppingBag },
  { href: '/account/promotions', labelKey: 'account.nav.promotions', icon: Rocket },
  { href: '/account/messages', labelKey: 'account.nav.messages', icon: MessageCircle },
  { href: '/account/notifications', labelKey: 'account.nav.notifications', icon: Bell },
  { href: '/account/favorites', labelKey: 'account.nav.favorites', icon: Heart },
  { href: '/account/saved-searches', labelKey: 'account.nav.saved_searches', icon: Bookmark },
  { href: '/account/recently-viewed', labelKey: 'account.nav.recently_viewed', icon: Clock },
  { href: '/account/support', labelKey: 'account.nav.support', icon: LifeBuoy },
];

export function isNavItemActive(pathname: string, href: string): boolean {
  if (href === ACCOUNT_HUB_PATH) return pathname === ACCOUNT_HUB_PATH;
  return pathname === href || pathname.startsWith(`${href}/`);
}

function isInSection(pathname: string, item: AccountNavItem): boolean {
  return item.exact ? pathname === item.href : isNavItemActive(pathname, item.href);
}

/** Settings routes render inside the sidebar shell; the others are full-width pages. */
export function isSettingsPath(pathname: string): boolean {
  return pathname === ACCOUNT_HUB_PATH || SETTINGS_NAV.some((item) => isInSection(pathname, item));
}

/** The settings section a path belongs to, for the breadcrumb and the phone title. */
export function settingsSectionFor(pathname: string): AccountNavItem {
  return SETTINGS_NAV.find((item) => isInSection(pathname, item)) ?? ACCOUNT_HUB_ITEM;
}
