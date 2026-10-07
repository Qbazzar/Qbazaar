import type { Metadata } from 'next';
import { Suspense } from 'react';

import { AuthFormSkeleton } from '@/components/auth/AuthFormSkeleton';
import { AuthHeading } from '@/components/auth/AuthHeading';
import { LoginForm } from '@/components/auth/LoginForm';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();

  return {
    title: t('auth.tabs.login', 'تسجيل الدخول'),
    description: t('auth.login.subtitle', 'سجّل دخولك إلى حساب QBazaar.'),
  };
}

export default async function LoginPage() {
  await resolveServerLocale();

  return (
    <>
      <AuthHeading
        title={
          <>
            {t('auth.login.welcome_prefix')} <span className="text-qb-brand">{t('auth.login.welcome_brand')}</span>
          </>
        }
        subtitle={t('auth.login.tagline')}
      />
      {/* useSearchParams() inside LoginForm requires a Suspense boundary
          so the page can stream during static generation. */}
      <Suspense fallback={<AuthFormSkeleton fields={2} />}>
        <LoginForm />
      </Suspense>
    </>
  );
}
