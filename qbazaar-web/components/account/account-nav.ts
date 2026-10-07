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
  Settings,
  ShieldCheck,
  ShieldHalf,
  Trash2,
  User,
  UserX,
  type LucideIcon,
} from 'lucide-react';

export interface AccountNavItem {
  href: string;
  labelKey: string;
  icon: LucideIcon;
  /** Destructive sections are listed in the danger colour (the design's "Delete Account"). */
  danger?: boolean;
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

/** Settings routes render inside the sidebar shell; the others are full-width pages. */
export function isSettingsPath(pathname: string): boolean {
  return pathname === ACCOUNT_HUB_PATH || SETTINGS_NAV.some((item) => isNavItemActive(pathname, item.href));
}

/** The settings section a path belongs to, for the breadcrumb and the phone title. */
export function settingsSectionFor(pathname: string): AccountNavItem {
  return SETTINGS_NAV.find((item) => isNavItemActive(pathname, item.href)) ?? ACCOUNT_HUB_ITEM;
}
