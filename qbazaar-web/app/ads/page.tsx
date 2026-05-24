import { Suspense } from 'react';
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
export const metadata = {
  title: 'الإعلانات',
};

export default function AdsListPage() {
  return (
    <Suspense fallback={null}>
      <AdsListClient />
    </Suspense>
  );
}
