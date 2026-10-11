'use client';

import Link from 'next/link';
import { LogOut } from 'lucide-react';

import { focusRing } from '@/components/design-system/focus-ring';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

import { SETTINGS_NAV, settingsSectionFor, type AccountNavItem } from './account-nav';
import { useSignOut } from './useSignOut';

/**
 * Settings menu of account.html (`.qb-navitem`): grey rows that slide and tint
 * on hover, the current one an orange pill, then "Log Out" under a line.
 */
export function SettingsSidebar({ pathname }: { pathname: string }) {
  const { signOut, pending } = useSignOut();
  const current = settingsSectionFor(pathname);

  return (
    <nav aria-label={t('account.nav.settings')}>
      <ul className="flex flex-col gap-1">
        {SETTINGS_NAV.map((item) => (
          <li key={item.href}>
            <SidebarLink item={item} active={item === current} />
          </li>
        ))}
      </ul>
      <button
        type="button"
        onClick={signOut}
        disabled={pending}
        className={cn(
          'mt-2 flex w-full cursor-pointer items-center gap-3 rounded-qb-lg border-t border-qb-line px-4 pt-[18px] pb-3.5 text-start text-qb-body-sm text-qb-acct-danger disabled:cursor-progress disabled:opacity-60',
          focusRing,
        )}
      >
        <LogOut aria-hidden="true" className="size-5 shrink-0 rtl:-scale-x-100" strokeWidth={1.6} />
        {t('account.nav.log_out')}
      </button>
    </nav>
  );
}

function SidebarLink({ item, active }: { item: AccountNavItem; active: boolean }) {
  const ItemIcon = item.icon;
  return (
    <Link
      href={item.href}
      aria-current={active ? 'page' : undefined}
      className={cn(
        'flex items-center gap-3 rounded-qb-lg px-4 py-3.5 text-qb-body-sm transition-[background-color,translate] duration-200 motion-reduce:transition-none',
        active
          ? 'bg-qb-brand font-semibold text-qb-on-brand'
          : 'font-medium text-qb-ink-secondary hover:translate-x-[5px] hover:bg-qb-brand-soft rtl:hover:-translate-x-[5px]',
        focusRing,
      )}
    >
      <ItemIcon aria-hidden="true" className="size-5 shrink-0" strokeWidth={1.6} />
      <span className="min-w-0">{t(item.labelKey)}</span>
    </Link>
  );
}
