import type { Metadata } from 'next';

import { ForgotPasswordForm } from '@/components/auth/ForgotPasswordForm';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();

  return {
    title: t('auth.forgot_password.title', 'نسيت كلمة المرور'),
    // The subtitle's line break only shapes the card.
    description: t('auth.forgot_password.subtitle', 'أرسل رابط إعادة تعيين كلمة المرور إلى بريدك.').replace(/\n/g, ' '),
  };
}

export default function ForgotPasswordPage() {
  return <ForgotPasswordForm />;
}
