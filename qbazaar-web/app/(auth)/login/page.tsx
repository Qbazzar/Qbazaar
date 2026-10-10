import type { Metadata } from 'next';

import { AuthHeading } from '@/components/auth/AuthHeading';
import { LoginJourney } from '@/components/auth/LoginJourney';
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
    <LoginJourney
      welcome={
        <AuthHeading
          title={
            <>
              {t('auth.login.welcome_prefix')} <span className="text-qb-brand">{t('auth.login.welcome_brand')}</span>
            </>
          }
          subtitle={t('auth.login.tagline')}
        />
      }
    />
  );
}
