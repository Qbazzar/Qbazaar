/**
 * `/search` — server-component shell. The client island reads the URL, so it
 * sits in a Suspense boundary whose fallback has the page's frame. The first
 * page of results is fetched here and seeded into the island's query.
 */
import { Suspense } from 'react';
import type { Metadata } from 'next';
import { HydrationBoundary } from '@tanstack/react-query';

import { CatalogPageSkeleton } from '@/components/catalog/CatalogPageSkeleton';
import { ResultsFocusProvider } from '@/components/catalog/results-focus';
import { QatarPlacesProvider } from '@/components/locations/QatarPlacesProvider';
import { fetchPlaces, searchSeed, type SeedParams } from '@/lib/catalog-seed';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';
import { pageMetadata } from '@/lib/page-metadata';
import { SearchClient } from './SearchClient';

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();

  // Result pages are one visitor's question, not content worth indexing.
  return pageMetadata({
    title: t('search.title', 'نتائج البحث'),
    path: '/search',
    robots: { index: false, follow: true },
  });
}

export default async function SearchPage({ searchParams }: { searchParams: Promise<SeedParams> }) {
  const [bag, locale] = await Promise.all([searchParams, resolveServerLocale()]);
  // The first page of results comes with the HTML; the client island reads the same query from the seed.
  const [seed, places] = await Promise.all([searchSeed(bag, locale), fetchPlaces()]);
  return (
    <Suspense fallback={<CatalogPageSkeleton title={t('search.title', 'نتائج البحث')} />}>
      <QatarPlacesProvider places={places ?? undefined}>
        <HydrationBoundary state={seed}>
          <ResultsFocusProvider>
            <SearchClient />
          </ResultsFocusProvider>
        </HydrationBoundary>
      </QatarPlacesProvider>
    </Suspense>
  );
}
