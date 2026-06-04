import type { Metadata } from 'next';
import { Suspense } from 'react';
import { resolveServerLocale } from '@/lib/i18n/server';
import { t } from '@/lib/i18n/messages';
import { VerifyEmailLanding } from '@/components/auth/VerifyEmailLanding';

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
 * Lives OUTSIDE the `(auth)` group on purpose: this is a result page, not an
 * auth form, so it gets its own minimal centered layout.
 */
export default function VerifyEmailPage() {
  return (
    <main className="bg-cream-50 flex min-h-svh items-center justify-center p-4 sm:p-8">
      {/* useSearchParams() inside the landing component requires Suspense. */}
      <Suspense fallback={<VerifyEmailSkeleton />}>
        <VerifyEmailLanding />
      </Suspense>
    </main>
  );
}

function VerifyEmailSkeleton() {
  return (
    <div
      className="bg-card border-border w-full max-w-md space-y-4 rounded-3xl border p-8 shadow-sm"
      aria-hidden="true"
    >
      <div className="bg-muted mx-auto size-12 rounded-full" />
      <div className="bg-muted mx-auto h-6 w-2/3 rounded" />
      <div className="bg-muted mx-auto h-4 w-3/4 rounded" />
    </div>
  );
}
