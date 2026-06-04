import { Suspense } from 'react';
import type { Metadata } from 'next';
import { resolveServerLocale } from '@/lib/i18n/server';
import { t } from '@/lib/i18n/messages';
import { NotificationsClient } from './NotificationsClient';

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();

  return {
    title: t('account.nav.notifications', 'الإشعارات'),
  };
}

/**
 * Server shell — wraps the client tabs page in Suspense so nuqs can read the
 * `?tab=` parameter without de-opting the route to dynamic rendering.
 */
export default function NotificationsPage() {
  return (
    <Suspense fallback={null}>
      <NotificationsClient />
    </Suspense>
  );
}
