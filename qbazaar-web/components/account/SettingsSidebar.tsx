'use client';

import Link from 'next/link';
import { LogOut } from 'lucide-react';

import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

import { ACCOUNT_HUB_ITEM, SETTINGS_NAV, isNavItemActive, type AccountNavItem } from './account-nav';
import { useSignOut } from './useSignOut';

/** "Settings" sidebar of 394:9270: grey rows, the current one as an orange pill. */
export function SettingsSidebar({ pathname }: { pathname: string }) {
  const { signOut, pending } = useSignOut();

  return (
    <nav aria-label={t('account.nav.settings')} className="flex h-full flex-col px-[17px] pt-8 pb-10 qb-desktop:px-6">
      <p aria-hidden="true" className="text-qb-h4 leading-none font-semibold text-qb-ink qb-desktop:text-qb-h3">
        {t('account.nav.settings')}
      </p>
      <ul className="mt-6 flex flex-col gap-2 qb-desktop:mt-9">
        {[ACCOUNT_HUB_ITEM, ...SETTINGS_NAV].map((item) => (
          <li key={item.href}>
            <SidebarLink item={item} active={isNavItemActive(pathname, item.href)} />
          </li>
        ))}
      </ul>
      <button
        type="button"
        onClick={signOut}
        disabled={pending}
        className={cn(
          'mt-8 flex h-12 items-center gap-2 rounded-qb-md text-qb-body font-medium text-qb-danger hover:bg-qb-danger-soft disabled:cursor-progress disabled:opacity-60',
          focusRing,
        )}
      >
        <Icon icon={LogOut} size="lg" flipInRtl />
        {t('account.nav.sign_out')}
      </button>
    </nav>
  );
}

function SidebarLink({ item, active }: { item: AccountNavItem; active: boolean }) {
  return (
    <Link
      href={item.href}
      aria-current={active ? 'page' : undefined}
      className={cn(
        'flex min-h-12 items-center gap-1.5 rounded-qb-md transition-colors qb-desktop:gap-2',
        active
          ? 'bg-qb-brand px-3 text-qb-caption font-semibold text-white shadow-qb-brand qb-desktop:px-4 qb-desktop:text-qb-body'
          : 'text-qb-body font-medium text-qb-ink-secondary hover:text-qb-brand',
        focusRing,
      )}
    >
      <Icon
        icon={item.icon}
        size="lg"
        className={cn('size-5 qb-desktop:size-6', active ? null : item.danger ? 'text-qb-danger' : 'text-qb-ink-secondary')}
      />
      <span>{t(item.labelKey)}</span>
    </Link>
  );
}
