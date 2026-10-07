import type { Metadata } from 'next';
import { Suspense } from 'react';

import { AuthCard } from '@/components/auth/AuthCard';
import { VerifyEmailLanding } from '@/components/auth/VerifyEmailLanding';
import { resolveServerLocale } from '@/lib/i18n/server';
import { t } from '@/lib/i18n/messages';

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();

  return {
    title: t('auth.verify_email.title', 'تأكيد البريد الإلكتروني'),
    description: 'صفحة تأكيد البريد الإلكتروني لـ QBazaar.',
  };
}

/**
 * Standalone landing page reached from the Laravel signed verification email.
 *
 * The design puts it in the auth shell (send-code.html), but it stays outside
 * the `(auth)` group until the site header and footer gates also skip
 * `/verify-email`; inside the group it would show both headers.
 */
export default function VerifyEmailPage() {
  return (
    <main className="flex justify-center bg-qb-page px-4 pt-10 pb-16 qb-tablet:px-20 qb-tablet:pt-28 qb-desktop:pt-[58px]">
      {/* useSearchParams() inside the landing component requires Suspense. */}
      <Suspense fallback={<VerifyEmailSkeleton />}>
        <VerifyEmailLanding />
      </Suspense>
    </main>
  );
}

function VerifyEmailSkeleton() {
  return (
    <AuthCard>
      <div aria-hidden="true" className="flex animate-pulse flex-col items-center gap-4 motion-reduce:animate-none">
        <div className="size-[72px] rounded-qb-2xl bg-qb-fill qb-tablet:size-[92px]" />
        <div className="h-8 w-2/3 rounded-qb-sm bg-qb-fill" />
        <div className="h-5 w-3/4 rounded-qb-sm bg-qb-fill" />
      </div>
    </AuthCard>
  );
}
