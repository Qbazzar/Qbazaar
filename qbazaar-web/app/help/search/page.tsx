import { Suspense } from 'react';
import type { Metadata } from 'next';
import { resolveServerLocale } from '@/lib/i18n/server';
import { t } from '@/lib/i18n/messages';
import { HelpSearchClient } from './HelpSearchClient';

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();

  return {
    title: t('help.title', 'مركز المساعدة'),
  };
}

export default function HelpSearchPage() {
  return (
    <Suspense fallback={null}>
      <HelpSearchClient />
    </Suspense>
  );
}
