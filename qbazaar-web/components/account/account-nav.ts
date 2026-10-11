import {
  CircleAlert,
  Mail,
  Settings,
  ShieldCheck,
  Trash2,
  User,
  Wallet,
  type LucideIcon,
} from 'lucide-react';

export interface AccountNavItem {
  href: string;
  labelKey: string;
  icon: LucideIcon;
  /** Only the page itself sits in the settings shell; its sub-pages are full-width pages. */
  exact?: boolean;
  /** Other settings pages opened from this section's rows ("Active sessions" under Account Settings). */
  subPages?: readonly string[];
}

export const ACCOUNT_HUB_PATH = '/account';

/**
 * The "Settings" menu of account.html (accountNavDefs): the sidebar from the
 * tablet layout up, the hub rows on phones. "Billing Info" has no API yet, so
 * it is left out.
 */
export const SETTINGS_NAV: readonly AccountNavItem[] = [
  { href: '/account/profile', labelKey: 'account.nav.profile_settings', icon: User },
  { href: '/account/wallet', labelKey: 'account.nav.wallet', icon: Wallet, exact: true },
  {
    href: '/account/security',
    labelKey: 'account.nav.account_settings',
    icon: Settings,
    subPages: ['/account/sessions', '/account/verification', '/account/email-change'],
  },
  {
    href: '/account/privacy',
    labelKey: 'account.nav.data_protection',
    icon: ShieldCheck,
    subPages: ['/account/blocked-users'],
  },
  { href: '/account/email-messages', labelKey: 'account.nav.email_message', icon: Mail },
  { href: '/account/marketplace', labelKey: 'account.nav.marketplace_info', icon: CircleAlert },
  { href: '/account/data', labelKey: 'account.nav.delete_account', icon: Trash2 },
];

/** The section the hub stands for from the tablet layout up, where `/account` opens on it. */
export const HUB_SECTION = SETTINGS_NAV[0];

export function isNavItemActive(pathname: string, href: string): boolean {
  if (href === ACCOUNT_HUB_PATH) return pathname === ACCOUNT_HUB_PATH;
  return pathname === href || pathname.startsWith(`${href}/`);
}

function isInSection(pathname: string, item: AccountNavItem): boolean {
  if (item.exact ? pathname === item.href : isNavItemActive(pathname, item.href)) return true;
  return item.subPages?.some((page) => isNavItemActive(pathname, page)) ?? false;
}

/** Settings routes render inside the sidebar shell; the others are full-width pages. */
export function isSettingsPath(pathname: string): boolean {
  return pathname === ACCOUNT_HUB_PATH || SETTINGS_NAV.some((item) => isInSection(pathname, item));
}

/** The settings section a path belongs to; the hub opens on Profile Settings. */
export function settingsSectionFor(pathname: string): AccountNavItem {
  return SETTINGS_NAV.find((item) => isInSection(pathname, item)) ?? HUB_SECTION;
}
