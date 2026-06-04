import { Suspense } from 'react';
import type { Metadata } from 'next';
import { resolveServerLocale } from '@/lib/i18n/server';
import { t } from '@/lib/i18n/messages';
import { MessagesClient } from './MessagesClient';

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();

  return {
    title: t('account.nav.messages', 'الرسائل'),
  };
}

/**
 * Inbox shell — Next 16 requires us to wrap any client component reaching
 * for `useSearchParams` (via nuqs) in a Suspense boundary on the server, so
 * the split-pane client lives inside one.
 */
export default function MessagesPage() {
  return (
    <Suspense fallback={null}>
      <MessagesClient />
    </Suspense>
  );
}
