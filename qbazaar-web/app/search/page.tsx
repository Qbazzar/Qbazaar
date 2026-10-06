/**
 * `/search` — server-component shell. The client island reads the URL, so it
 * sits in a Suspense boundary whose fallback has the page's frame.
 */
import { Suspense } from 'react';
import type { Metadata } from 'next';

import { CatalogPageSkeleton } from '@/components/catalog/CatalogPageSkeleton';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';
import { SearchClient } from './SearchClient';

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();

  return {
    title: t('search.title', 'نتائج البحث'),
  };
}

export default async function SearchPage() {
  await resolveServerLocale();
  return (
    <Suspense fallback={<CatalogPageSkeleton title={t('search.title', 'نتائج البحث')} />}>
      <SearchClient />
    </Suspense>
  );
}
