import { Suspense } from 'react';
import type { Metadata } from 'next';

import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';

import { HelpSearchClient } from './HelpSearchClient';

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();

  return {
    title: `${t('help.search_title')} · ${t('help.title')}`,
    // Result pages repeat the articles; only the articles themselves should be indexed.
    robots: { index: false, follow: true },
  };
}

export default function HelpSearchPage() {
  return (
    <Suspense fallback={null}>
      <HelpSearchClient />
    </Suspense>
  );
}
