'use client';

/**
 * Account layout — wraps every authenticated `/account/...` page.
 *
 * - Owns the `<main>` landmark, so the pages inside render none of their own.
 * - Settings sections render inside the "Settings" sidebar shell; My Ads,
 *   messages, notifications and the saved lists are full-width pages.
 * - Client-side guard via `useRequireAuth`. While the store hydrates we paint
 *   a spinner so the page doesn't flash empty.
 */
import type { ReactNode } from 'react';
import { usePathname } from 'next/navigation';
import { Loader2 } from 'lucide-react';

import { SettingsShell } from '@/components/account/SettingsShell';
import { isSettingsPath } from '@/components/account/account-nav';
import { useRequireAuth } from '@/hooks/useRequireAuth';
import { t } from '@/lib/i18n/messages';

export default function AccountLayout({ children }: { children: ReactNode }) {
  const { user, isLoading } = useRequireAuth();
  const pathname = usePathname() ?? '/account';
  const page = isSettingsPath(pathname) ? <SettingsShell>{children}</SettingsShell> : children;

  return <main className="bg-qb-page font-qb text-qb-ink">{isLoading || !user ? <AccountLoading /> : page}</main>;
}

function AccountLoading() {
  return (
    <div className="flex min-h-svh items-center justify-center" role="status">
      <Loader2 className="size-6 animate-spin text-qb-ink-subtle motion-reduce:animate-none" aria-hidden="true" />
      <span className="sr-only">{t('common.loading')}</span>
    </div>
  );
}
