'use client';

import Link from 'next/link';

import { focusRing } from '@/components/design-system/focus-ring';
import { LogOutIcon } from '@/components/design-system/icons';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

import { SETTINGS_NAV, settingsSectionFor, type AccountNavItem } from './account-nav';
import { useSignOut } from './useSignOut';

/**
 * Settings menu of account.html (`.qb-navitem`): grey rows 4 px apart that
 * slide 5 px and tint on hover (background and transform over .18 s), the
 * current one an orange pill, then the "Log Out" `.qb-btn` under a line.
 */
export function SettingsSidebar({ pathname }: { pathname: string }) {
  const { signOut, pending } = useSignOut();
  const current = settingsSectionFor(pathname);

  return (
    <nav aria-label={t('account.nav.settings')}>
      <ul className="flex flex-col">
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
          'qb-motion mt-2 flex w-full cursor-pointer items-center gap-3 rounded-qb-lg border-t border-qb-line px-4 pt-[18px] pb-3.5 text-start text-qb-body-sm leading-[1.15] text-qb-acct-danger hover:brightness-[.96] disabled:cursor-progress disabled:opacity-60',
          focusRing,
        )}
      >
        <LogOutIcon aria-hidden="true" className="size-[18px] shrink-0 rtl:-scale-x-100" />
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
        'mb-1 flex items-center gap-3 rounded-qb-lg px-4 py-3.5 text-qb-body-sm transition-[background,transform] duration-[180ms] ease-[ease] motion-reduce:transition-none',
        active
          ? 'bg-qb-brand font-semibold text-qb-on-brand'
          : 'font-medium text-qb-ink-secondary hover:bg-qb-brand-soft hover:[transform:translateX(5px)] rtl:hover:[transform:translateX(-5px)]',
        focusRing,
      )}
    >
      <ItemIcon aria-hidden="true" className="size-5 shrink-0" strokeWidth={1.6} />
      <span className="min-w-0">{t(item.labelKey)}</span>
    </Link>
  );
}
