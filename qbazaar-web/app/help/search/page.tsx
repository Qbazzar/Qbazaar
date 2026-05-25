import { Suspense } from 'react';
import type { Metadata } from 'next';
import { HelpSearchClient } from './HelpSearchClient';

export const metadata: Metadata = {
  title: 'Help search',
};

export default function HelpSearchPage() {
  return (
    <Suspense fallback={null}>
      <HelpSearchClient />
    </Suspense>
  );
}
