import { Suspense } from 'react';
import type { Metadata } from 'next';
import { MyTicketsClient } from './MyTicketsClient';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();

  return {
    title: t('support.my_tickets', 'تذاكر الدعم'),
  };
}

export default function MyTicketsPage() {
  return (
    <Suspense fallback={null}>
      <MyTicketsClient />
    </Suspense>
  );
}
