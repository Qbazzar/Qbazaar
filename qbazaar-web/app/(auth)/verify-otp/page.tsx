import type { Metadata } from 'next';
import { Suspense } from 'react';
import { VerifyOtpForm } from '@/components/auth/VerifyOtpForm';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();

  return {
    title: t('auth.verify_otp.title', 'تأكيد رقم الهاتف'),
  };
}

export default function VerifyOtpPage() {
  return (
    <div className="space-y-6">
      <header className="space-y-2">
        <h1 className="font-display text-3xl tracking-tight">
          {t('auth.verify_otp.title')}
        </h1>
      </header>
      {/* useSearchParams() inside the form requires a Suspense boundary. */}
      <Suspense fallback={<VerifyOtpSkeleton />}>
        <VerifyOtpForm />
      </Suspense>
    </div>
  );
}

function VerifyOtpSkeleton() {
  return (
    <div className="space-y-4" aria-hidden="true">
      <div className="bg-muted h-4 w-3/4 rounded" />
      <div className="flex justify-center gap-2">
        {Array.from({ length: 6 }).map((_, i) => (
          <div key={i} className="bg-muted h-12 w-10 rounded-lg sm:h-14 sm:w-12" />
        ))}
      </div>
      <div className="bg-muted h-11 rounded-full" />
    </div>
  );
}
