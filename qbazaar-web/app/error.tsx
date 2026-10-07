'use client';

import { useEffect } from 'react';

import { ErrorView } from '@/components/status/ErrorView';

/**
 * Route-level error boundary: a recoverable message instead of a blank
 * screen. No Figma frame: built like "Search Not Found" (655:55973).
 */
export default function Error({
  error,
  unstable_retry,
}: {
  error: Error & { digest?: string };
  /** Re-fetches and re-renders the segment (Next.js 16.2), so server errors can recover too. */
  unstable_retry: () => void;
}) {
  useEffect(() => {
    // Surface to the console (and any wired error tracker) for diagnosis.
    console.error(error);
  }, [error]);

  return <ErrorView onRetry={unstable_retry} />;
}
