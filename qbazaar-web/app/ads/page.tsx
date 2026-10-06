import type { Metadata } from 'next';
import { Suspense } from 'react';

import { CatalogPageSkeleton } from '@/components/catalog/CatalogPageSkeleton';
import { resolveServerLocale } from '@/lib/i18n/server';
import { t } from '@/lib/i18n/messages';
import { AdsListClient } from './AdsListClient';

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();

  return {
    title: t('ads.list.title', 'الإعلانات'),
  };
}

/**
 * `/ads` — paginated public listing. The listing reads the URL, so Next 16
 * needs it inside a Suspense boundary to prerender the page shell.
 */
export default async function AdsListPage() {
  await resolveServerLocale();
  return (
    <Suspense fallback={<CatalogPageSkeleton title={t('ads.list.title', 'كل الإعلانات')} />}>
      <AdsListClient />
    </Suspense>
  );
}
