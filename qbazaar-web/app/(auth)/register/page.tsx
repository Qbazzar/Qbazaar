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
      <h1 className="font-display text-ink-900 mb-2 text-3xl tracking-tight sm:text-4xl">
        {t('auth.register.title', 'أنشئ حسابك')}
      </h1>
      <p className="text-ink-700 mb-7 text-sm">
        {t('auth.register.subtitle', 'سجّل في QBazaar للنشر والشراء.')}
      </p>
      <RegisterForm />
    </>
  );
}
