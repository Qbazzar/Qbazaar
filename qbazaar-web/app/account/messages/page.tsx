import { Suspense } from 'react';
import type { Metadata } from 'next';
import { MessagesClient } from './MessagesClient';

export const metadata: Metadata = {
  title: 'Messages',
};

/**
 * Inbox shell — Next 16 requires us to wrap any client component reaching
 * for `useSearchParams` (via nuqs) in a Suspense boundary on the server, so
 * the split-pane client lives inside one.
 */
export default function MessagesPage() {
  return (
    <Suspense fallback={null}>
      <MessagesClient />
    </Suspense>
  );
}
