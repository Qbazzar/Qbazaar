import Image from 'next/image';
import Link from 'next/link';
import type { ReactNode } from 'react';

import { AuthCard } from '@/components/auth/AuthCard';
import { focusRing } from '@/components/design-system/focus-ring';
import { pageGutter } from '@/components/design-system/page-gutter';
import { LocaleSwitcher } from '@/components/i18n/LocaleSwitcher';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';
import { cn } from '@/lib/utils';

/**
 * Auth shell of the new design (`.qb-ahead` + `.qb-card`, frames 736:65208,
 * 779:39974, 779:40299): logo and language button on a white bar, the form in
 * one centred card.
 */
export default async function AuthLayout({ children }: { children: ReactNode }) {
  // Segments render in parallel with the root layout, so prime the locale here too.
  await resolveServerLocale();

  return (
    <div className="flex min-h-svh flex-col bg-qb-page font-qb text-qb-ink">
      <header className="sticky top-0 z-10 bg-qb-surface shadow-qb-raised">
        <div
          className={cn(
            'mx-auto flex h-[72px] max-w-[1440px] items-center justify-between qb-tablet:h-[98px] qb-desktop:h-[105px]',
            pageGutter,
          )}
        >
          <Link
            href="/"
            aria-label={t('brand.name')}
            className={cn(
              'block h-[43px] w-[114px] rounded-qb-sm qb-tablet:h-[50px] qb-tablet:w-[133px] qb-desktop:h-[66px] qb-desktop:w-[175px]',
              focusRing,
            )}
          >
            <Image src="/brand/qb-logo.svg" alt="" width={175} height={66} preload unoptimized className="size-full" />
          </Link>
          <LocaleSwitcher
            className={cn(
              'inline-flex h-[37px] min-w-[82px] items-center justify-center rounded-qb-sm border border-qb-line bg-qb-surface px-4 font-qb text-qb-caption font-medium text-qb-ink-body hover:bg-qb-hover qb-tablet:h-[45px] qb-tablet:min-w-[97px]',
              focusRing,
            )}
          />
        </div>
      </header>
      {/* Centred in the space under the bar, as auth.css does: short cards (forgot password) sit lower than long ones. */}
      <main className="flex flex-1 items-center justify-center px-4 py-10 qb-tablet:px-20">
        <AuthCard>{children}</AuthCard>
      </main>
    </div>
  );
}
