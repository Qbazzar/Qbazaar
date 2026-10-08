import type { Metadata } from 'next';
import { Suspense } from 'react';

import { VerifyEmailLanding } from '@/components/auth/VerifyEmailLanding';
import { resolveServerLocale } from '@/lib/i18n/server';
import { t } from '@/lib/i18n/messages';

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();

  return {
    title: t('auth.verify_email.title', 'تأكيد البريد الإلكتروني'),
    description: t('auth.verify_email.meta_description', 'صفحة تأكيد البريد الإلكتروني لـ QBazaar.'),
  };
}

/** Landing page reached from the Laravel signed verification email, in the auth shell (send-code.html). */
export default function VerifyEmailPage() {
  return (
    // useSearchParams() inside the landing component requires Suspense.
    <Suspense fallback={<VerifyEmailSkeleton />}>
      <VerifyEmailLanding />
    </Suspense>
  );
}

function VerifyEmailSkeleton() {
  return (
    <div aria-hidden="true" className="flex animate-pulse flex-col items-center gap-4 motion-reduce:animate-none">
      <div className="size-[72px] rounded-qb-2xl bg-qb-fill qb-tablet:size-[92px]" />
      <div className="h-8 w-2/3 rounded-qb-sm bg-qb-fill" />
      <div className="h-5 w-3/4 rounded-qb-sm bg-qb-fill" />
    </div>
  );
}
