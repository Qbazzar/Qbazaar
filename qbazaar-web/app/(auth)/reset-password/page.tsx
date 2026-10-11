import type { Metadata } from 'next';
import { Suspense } from 'react';

import { AuthFormSkeleton } from '@/components/auth/AuthFormSkeleton';
import { ResetPasswordForm } from '@/components/auth/ResetPasswordForm';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();

  return {
    title: t('auth.reset_password.title', 'تعيين كلمة مرور جديدة'),
    // The subtitle's line break only shapes the card.
    description: t('auth.reset_password.subtitle', 'اختر كلمة مرور جديدة لحسابك.').replace(/\n/g, ' '),
  };
}

export default function ResetPasswordPage() {
  return (
    // useSearchParams() inside the form requires a Suspense boundary.
    <Suspense fallback={<AuthFormSkeleton fields={2} />}>
      <ResetPasswordForm />
    </Suspense>
  );
}
