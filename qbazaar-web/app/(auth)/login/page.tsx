import type { Metadata } from 'next';
import { Suspense } from 'react';
import { LoginForm } from '@/components/auth/LoginForm';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';
import { AuthTabs } from '@/components/auth/AuthTabs';

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();

  return {
    title: t('auth.tabs.login', 'تسجيل الدخول'),
    description: t('auth.login.subtitle', 'سجّل دخولك إلى حساب QBazaar.'),
  };
}

export default function LoginPage() {
  return (
    <>
      <AuthTabs active="login" />
      <h1 style={{ margin: '0 0 8px', fontSize: 36, fontWeight: 800 }}>
        {t('auth.login.title', 'مرحباً بعودتك')}
      </h1>
      <p
        style={{
          margin: '0 0 28px',
          fontSize: '14.5px',
          color: 'var(--ink-700)',
        }}
      >
        {t('auth.login.subtitle', 'سجّل دخولك إلى حساب QBazaar.')}
      </p>
      {/* useSearchParams() inside LoginForm requires a Suspense boundary
          so the page can stream during static generation. */}
      <Suspense fallback={<LoginFormSkeleton />}>
        <LoginForm />
      </Suspense>
    </>
  );
}

function LoginFormSkeleton() {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }} aria-hidden="true">
      <div className="field animate-pulse" style={{ height: 44 }} />
      <div className="field animate-pulse" style={{ height: 44 }} />
      <div className="btn btn--primary btn--lg btn--full animate-pulse" style={{ opacity: 0.5 }} />
    </div>
  );
}
