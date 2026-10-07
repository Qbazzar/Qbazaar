import type { Metadata } from 'next';

import { AuthHeading } from '@/components/auth/AuthHeading';
import { RegisterForm } from '@/components/auth/RegisterForm';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();

  return {
    title: t('auth.tabs.register', 'إنشاء حساب'),
    description: t('auth.register.subtitle', 'أنشئ حساباً جديداً على QBazaar.'),
  };
}

export default async function RegisterPage() {
  await resolveServerLocale();

  return (
    <>
      <AuthHeading title={t('auth.register.heading')} subtitle={t('auth.register.tagline')} />
      <RegisterForm />
    </>
  );
}
