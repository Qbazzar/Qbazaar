import type { Metadata } from 'next';
import { Suspense } from 'react';
import { resolveServerLocale } from '@/lib/i18n/server';
import { t } from '@/lib/i18n/messages';
import { AdsListClient } from './AdsListClient';

/**
 * `/ads` — paginated public listing with optional category + location filters.
 *
 * Server component renders the shell; the filter sidebar + grid live in a
 * client island so they can read the URL search params and re-fetch on
 * change.
 *
 * The Suspense boundary is required by Next 16 because AdsListClient reads
 * `useSearchParams()` — without it, static prerendering bails the page.
 */
export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();

  return {
    title: t('ads.list.title', 'الإعلانات'),
  };
}

export default function AdsListPage() {
  return (
    <Suspense fallback={null}>
      <AdsListClient />
    </Suspense>
  );
}
