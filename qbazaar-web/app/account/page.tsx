'use client';

/**
 * Settings hub. Phones get the hub of acct.js (613:28184): who is signed in,
 * then one row per settings section. From the tablet layout up the menu is
 * on screen, so `/account` opens on Profile Settings, as account.html does.
 */
import Link from 'next/link';

import { focusRing } from '@/components/design-system/focus-ring';
import { NamedAvatar } from '@/components/account/NamedAvatar';
import { ProfileSettingsPanel } from '@/components/account/ProfileSettingsPanel';
import { HUB_SECTION, SETTINGS_NAV } from '@/components/account/account-nav';
import { useAuth } from '@/hooks/useAuth';
import type { User } from '@/lib/api/types';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

export default function AccountHubPage() {
  const { user } = useAuth();
  if (!user) return null;

  return (
    <>
      <PhoneHub user={user} />
      <div className="hidden qb-tablet:block">
        <ProfileSettingsPanel />
      </div>
    </>
  );
}

/** Full-bleed white sheet of the phone hub: identity row, then the sections with a chevron each. */
function PhoneHub({ user }: { user: User }) {
  return (
    <nav aria-label={t('account.nav.settings')} className="-mx-4 bg-qb-surface px-6 pt-[22px] pb-7 qb-tablet:hidden">
      <div className="mb-3 flex items-center gap-3.5 border-b border-qb-acct-hub-divider px-1 pt-1.5 pb-[18px]">
        <NamedAvatar
          name={user.full_name}
          src={user.avatar_url}
          tone="neutral"
          className="size-14 bg-qb-acct-avatar text-[18px] text-qb-ink-body"
        />
        <div className="min-w-0">
          <p className="truncate text-qb-h5 font-semibold text-qb-ink">{user.full_name}</p>
          <p className="mt-0.5 truncate text-qb-caption text-qb-ink-subtle">
            <span dir="ltr">{user.email}</span>
          </p>
        </div>
      </div>
      <ul className="flex flex-col gap-1">
        {SETTINGS_NAV.map((item) => {
          const ItemIcon = item.icon;
          return (
            <li key={item.href}>
              <Link
                href={item.href}
                className={cn(
                  'flex items-center gap-3 rounded-qb-md px-0.5 py-4 text-qb-body font-medium',
                  item === HUB_SECTION ? 'text-qb-ink' : 'text-qb-ink-secondary',
                  focusRing,
                )}
              >
                <ItemIcon aria-hidden="true" className="size-[22px] shrink-0" strokeWidth={1.6} />
                <span className="min-w-0 flex-1">{t(item.labelKey)}</span>
                <span aria-hidden="true" className="inline-block text-qb-h5 leading-none font-normal text-qb-acct-chevron rtl:-scale-x-100">
                  ›
                </span>
              </Link>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}
