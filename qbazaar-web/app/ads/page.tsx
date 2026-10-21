import type { Metadata } from 'next';
import { Suspense } from 'react';
import { HydrationBoundary } from '@tanstack/react-query';

import { CatalogPageSkeleton } from '@/components/catalog/CatalogPageSkeleton';
import { ResultsFocusProvider } from '@/components/catalog/results-focus';
import { QatarPlacesProvider } from '@/components/locations/QatarPlacesProvider';
import { fetchPlaces, listingSeed, type SeedParams } from '@/lib/catalog-seed';
import { resolveServerLocale } from '@/lib/i18n/server';
import { pageMetadata } from '@/lib/page-metadata';
import { t } from '@/lib/i18n/messages';
import { AdsListClient } from './AdsListClient';

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();

  return pageMetadata({ title: t('ads.list.title', 'الإعلانات'), path: '/ads' });
}

/**
 * `/ads` — paginated public listing. The listing reads the URL, so Next 16
 * needs it inside a Suspense boundary to prerender the page shell.
 */
export default async function AdsListPage({ searchParams }: { searchParams: Promise<SeedParams> }) {
  const [bag, locale] = await Promise.all([searchParams, resolveServerLocale()]);
  const [seed, places] = await Promise.all([listingSeed(bag, undefined, locale), fetchPlaces()]);
  return (
    <Suspense fallback={<CatalogPageSkeleton title={t('ads.list.title', 'كل الإعلانات')} />}>
      <QatarPlacesProvider places={places ?? undefined}>
        <HydrationBoundary state={seed}>
          <ResultsFocusProvider>
            <AdsListClient />
          </ResultsFocusProvider>
        </HydrationBoundary>
      </QatarPlacesProvider>
    </Suspense>
  );
}
