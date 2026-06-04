import type { Metadata } from 'next';
import { Suspense } from 'react';
import { ResetPasswordForm } from '@/components/auth/ResetPasswordForm';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();

  return {
    title: t('auth.reset_password.title', 'تعيين كلمة مرور جديدة'),
    description: t('auth.reset_password.subtitle', 'اختر كلمة مرور جديدة لحسابك.'),
  };
}

export default function ResetPasswordPage() {
  return (
    <div className="space-y-6">
      <header className="space-y-2">
        <h1 className="font-display text-3xl tracking-tight">
          {t('auth.reset_password.title')}
        </h1>
      </header>
      {/* useSearchParams() inside the form requires a Suspense boundary. */}
      <Suspense fallback={<ResetPasswordSkeleton />}>
        <ResetPasswordForm />
      </Suspense>
    </div>
  );
}

function ResetPasswordSkeleton() {
  return (
    <div className="space-y-4" aria-hidden="true">
      <div className="bg-muted h-16 rounded-lg" />
      <div className="bg-muted h-16 rounded-lg" />
      <div className="bg-muted h-11 rounded-full" />
    </div>
  );
}
