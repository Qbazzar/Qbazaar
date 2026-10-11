'use client';

import type { ReactNode } from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';

import { focusRing } from '@/components/design-system/focus-ring';
import { pageGutter } from '@/components/design-system/page-gutter';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

import { ACCOUNT_HUB_PATH } from './account-nav';
import { SettingsSidebar } from './SettingsSidebar';

/**
 * Settings screen of account.html: the "Settings" title on the grey page,
 * the white menu card beside the open panel (210 px rail on tablets). Phones
 * drop the menu: the hub at `/account` lists the sections, and each section
 * opens on its own screen with a "‹ Settings" pill back to it.
 */
export function SettingsShell({ children }: { children: ReactNode }) {
  const pathname = usePathname() ?? ACCOUNT_HUB_PATH;
  const isHub = pathname === ACCOUNT_HUB_PATH;

  return (
    <div className={cn('mx-auto max-w-[1440px] py-[clamp(20px,4vw,40px)] font-qb text-qb-ink', pageGutter)}>
      <h1 className="mb-6 text-[clamp(28px,4vw,40px)] leading-normal font-semibold tracking-normal">
        {t('account.nav.settings')}
      </h1>
      <div className="flex items-start gap-6 qb-desktop:flex-wrap">
        <aside className="hidden shrink-0 rounded-qb-xl border border-qb-line bg-qb-surface p-4 qb-tablet:block qb-tablet:w-[210px] qb-desktop:w-auto qb-desktop:max-w-[320px] qb-desktop:min-w-[240px] qb-desktop:flex-[1_1_260px]">
          <SettingsSidebar pathname={pathname} />
        </aside>
        <div className="flex min-w-0 flex-1 flex-col gap-5 qb-desktop:min-w-[300px] qb-desktop:flex-[3_1_460px]">
          {isHub ? null : (
            <Link
              href={ACCOUNT_HUB_PATH}
              className={cn(
                'mt-0.5 mb-1 inline-flex items-center gap-2 self-start rounded-qb-md border border-qb-line bg-qb-surface px-[15px] py-[9px] text-qb-label font-semibold text-qb-ink-title qb-tablet:hidden',
                focusRing,
              )}
            >
              <span aria-hidden="true" className="inline-block rtl:-scale-x-100">
                ‹
              </span>
              {t('account.nav.settings')}
            </Link>
          )}
          {children}
        </div>
      </div>
    </div>
  );
}
