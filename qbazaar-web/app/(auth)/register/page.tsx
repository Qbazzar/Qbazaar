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
