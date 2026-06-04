import type { Metadata } from 'next';
import { RegisterForm } from '@/components/auth/RegisterForm';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';
import { AuthTabs } from '@/components/auth/AuthTabs';

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();

  return {
    title: t('auth.tabs.register', 'إنشاء حساب'),
    description: t('auth.register.subtitle', 'أنشئ حساباً جديداً على QBazaar.'),
  };
}

export default function RegisterPage() {
  return (
    <>
      <AuthTabs active="register" />
      <h1 style={{ margin: '0 0 8px', fontSize: 36, fontWeight: 800 }}>
        {t('auth.register.title', 'أنشئ حسابك')}
      </h1>
      <p
        style={{
          margin: '0 0 28px',
          fontSize: '14.5px',
          color: 'var(--ink-700)',
        }}
      >
        {t('auth.register.subtitle', 'سجّل في QBazaar للنشر والشراء.')}
      </p>
      <RegisterForm />
    </>
  );
}
