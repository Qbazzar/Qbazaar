import type { Metadata } from 'next';
import { Suspense } from 'react';
import { LoginForm } from '@/components/auth/LoginForm';
import { t } from '@/lib/i18n/messages';
import { AuthTabs } from '@/components/auth/AuthTabs';

export const metadata: Metadata = {
  title: 'تسجيل الدخول',
  description: 'سجّل دخولك إلى حساب QBazaar.',
};

export default function LoginPage() {
  return (
    <div className="space-y-6">
      <AuthTabs active="login" />
      <header className="space-y-2">
        <h1 className="font-display text-3xl tracking-tight">
          {t('auth.login.title')}
        </h1>
        <p className="text-muted-foreground text-sm">
          {t('auth.login.subtitle')}
        </p>
      </header>
      {/* useSearchParams() inside LoginForm requires a Suspense boundary
          so the page can stream during static generation. */}
      <Suspense fallback={<LoginFormSkeleton />}>
        <LoginForm />
      </Suspense>
    </div>
  );
}

function LoginFormSkeleton() {
  return (
    <div className="space-y-4" aria-hidden="true">
      <div className="bg-muted h-16 rounded-lg" />
      <div className="bg-muted h-16 rounded-lg" />
      <div className="bg-muted h-11 rounded-full" />
    </div>
  );
}
