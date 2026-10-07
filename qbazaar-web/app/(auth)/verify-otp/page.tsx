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
    // useSearchParams() inside the form requires a Suspense boundary.
    <Suspense fallback={<VerifyOtpSkeleton />}>
      <VerifyOtpForm />
    </Suspense>
  );
}

function VerifyOtpSkeleton() {
  return (
    <div aria-hidden="true" className="flex animate-pulse flex-col items-center gap-5 motion-reduce:animate-none">
      <div className="size-[72px] rounded-qb-2xl bg-qb-fill qb-tablet:size-[92px]" />
      <div className="h-8 w-3/5 rounded-qb-sm bg-qb-fill" />
      <div className="h-5 w-4/5 rounded-qb-sm bg-qb-fill" />
      <div className="mt-6 flex w-full justify-center gap-2 qb-tablet:gap-3">
        {Array.from({ length: 6 }, (_, index) => (
          <div key={index} className="h-[52px] max-w-12 flex-1 rounded-qb-md bg-qb-fill qb-tablet:h-16 qb-tablet:max-w-[74px]" />
        ))}
      </div>
      <div className="mt-6 h-[52px] w-full rounded-qb-md bg-qb-fill" />
    </div>
  );
}
