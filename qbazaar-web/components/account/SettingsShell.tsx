'use client';

import type { ReactNode } from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { ArrowLeft } from 'lucide-react';

import { Breadcrumb } from '@/components/design-system/Breadcrumb';
import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { pageGutter } from '@/components/design-system/page-gutter';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

import { ACCOUNT_HUB_PATH, settingsSectionFor } from './account-nav';
import { SettingsSidebar } from './SettingsSidebar';

/**
 * Settings shell of `account.html` (394:9270, 561:30374, 613:32391): a grey band
 * with the breadcrumb (the page title on phones), then the white "Settings"
 * sidebar next to the section panel. Phones drop the sidebar: the hub at
 * `/account` lists the sections and each section opens on its own screen.
 */
export function SettingsShell({ children }: { children: ReactNode }) {
  const pathname = usePathname() ?? ACCOUNT_HUB_PATH;
  const section = settingsSectionFor(pathname);
  const isHub = pathname === ACCOUNT_HUB_PATH;

  return (
    <div className="bg-qb-page font-qb text-qb-ink">
      <div
        className={cn(
          'mx-auto max-w-[1440px] pt-9 pb-[54px] qb-tablet:pt-[72px] qb-tablet:pb-[65px] qb-desktop:pt-[65px] qb-desktop:pb-[49px]',
          pageGutter,
        )}
      >
        <Breadcrumb
          items={[{ label: t('home.breadcrumb'), href: '/' }, { label: t(section.labelKey) }]}
          className="hidden qb-tablet:block"
        />
        <div className="flex items-center gap-2 qb-tablet:hidden">
          {isHub ? null : (
            <Link
              href={ACCOUNT_HUB_PATH}
              aria-label={t('account.nav.back_to_settings')}
              className={cn('-ms-2 inline-flex size-10 items-center justify-center rounded-qb-md text-qb-ink hover:bg-qb-fill', focusRing)}
            >
              <Icon icon={ArrowLeft} size="lg" flipInRtl />
            </Link>
          )}
          <p className="text-qb-h2 leading-none font-semibold">{t('account.nav.settings')}</p>
        </div>
      </div>
      <div className="bg-qb-surface">
        <div className="mx-auto flex min-h-[640px] max-w-[1440px] qb-desktop:min-h-[777px]">
          <aside className="hidden w-[202px] shrink-0 shadow-qb-soft qb-tablet:block qb-desktop:w-[283px]">
            <SettingsSidebar pathname={pathname} />
          </aside>
          <div className="min-w-0 flex-1 border-t border-qb-line px-[17px] pt-[26px] pb-12 shadow-qb-soft qb-tablet:border-t-0 qb-tablet:px-6 qb-tablet:pt-8 qb-desktop:px-10">
            {children}
          </div>
        </div>
      </div>
    </div>
  );
}
