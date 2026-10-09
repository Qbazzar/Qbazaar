import Image from 'next/image';
import Link from 'next/link';
import type { Metadata } from 'next';
import type { ReactNode } from 'react';

import { AuthCard } from '@/components/auth/AuthCard';
import { AuthLanguagePill } from '@/components/auth/AuthLanguagePill';
import { focusRing } from '@/components/design-system/focus-ring';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';
import { cn } from '@/lib/utils';

export const metadata: Metadata = { robots: { index: false, follow: false } };

/**
 * Auth shell of the reference (`.qb-ahead` + `main` + `.qb-card` in
 * auth.css): a 76 px white bar with the logo and the language pill, the form
 * in one centred card.
 */
export default async function AuthLayout({ children }: { children: ReactNode }) {
  // Segments render in parallel with the root layout, so prime the locale here too.
  await resolveServerLocale();

  return (
    <div className="flex min-h-svh flex-col bg-qb-page font-qb text-qb-ink">
      <header className="sticky top-0 z-10 flex items-center justify-between bg-qb-surface px-4 py-2.5 shadow-qb-header qb-tablet:px-10">
        <Link href="/" aria-label={t('brand.name')} className={cn('block rounded-qb-sm', focusRing)}>
          <Image src="/brand/qb-logo.svg" alt="" width={149} height={56} preload unoptimized className="block h-14 w-auto" />
        </Link>
        <AuthLanguagePill />
      </header>
      {/* Centred in the space under the bar, as auth.css does: short cards (forgot password) sit lower than long ones. */}
      <main className="flex flex-1 items-center justify-center px-5 py-12">
        <AuthCard>{children}</AuthCard>
      </main>
    </div>
  );
}
