/**
 * `/search` — server-component shell.
 *
 * Wraps the client island in a Suspense boundary because Next 16 disallows
 * `useSearchParams` outside of one. The skeleton mirrors the client layout
 * so the page doesn't pop when the client tree mounts.
 */
import { Suspense } from 'react';
import type { Metadata } from 'next';
import { SearchClient } from './SearchClient';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();

  return {
    title: t('search.title', 'نتائج البحث'),
  };
}

export default function SearchPage() {
  return (
    <main className="bg-cream-50 min-h-svh">
      <Suspense fallback={<SearchSkeleton />}>
        <SearchClient />
      </Suspense>
    </main>
  );
}

function SearchSkeleton() {
  return (
    <div className="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6">
      <div className="mb-6 space-y-2">
        <div className="bg-cream-200 h-8 w-1/2 animate-pulse rounded-md" />
        <div className="bg-cream-200 h-4 w-1/3 animate-pulse rounded-md" />
      </div>
      <div className="grid gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
        <aside className="space-y-4">
          {Array.from({ length: 4 }).map((_, index) => (
            <div
              key={index}
              className="bg-cream-200 h-32 animate-pulse rounded-xl"
            />
          ))}
        </aside>
        <section className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          {Array.from({ length: 8 }).map((_, index) => (
            <div
              key={index}
              className="bg-cream-200 h-64 animate-pulse rounded-xl"
            />
          ))}
        </section>
      </div>
    </div>
  );
}
