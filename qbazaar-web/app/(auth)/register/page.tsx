import type { Metadata } from 'next';
import { RegisterForm } from '@/components/auth/RegisterForm';
import { t } from '@/lib/i18n/messages';
import { AuthTabs } from '@/components/auth/AuthTabs';

export const metadata: Metadata = {
  title: 'إنشاء حساب',
  description: 'أنشئ حساباً جديداً على QBazaar.',
};

export default function RegisterPage() {
  return (
    <div className="space-y-6">
      <AuthTabs active="register" />
      <header className="space-y-2">
        <h1 className="font-display text-3xl tracking-tight">
          {t('auth.register.title')}
        </h1>
        <p className="text-muted-foreground text-sm">
          {t('auth.register.subtitle')}
        </p>
      </header>
      <RegisterForm />
    </div>
  );
}
