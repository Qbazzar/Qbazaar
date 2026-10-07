'use client';

import { useCallback, useState } from 'react';

/**
 * A retry the user started on a failed query. While a query without data
 * refetches, TanStack Query reports it pending rather than failed, so the
 * failure panel, and the keyboard focus on its button, would give way to a
 * skeleton; `retrying` keeps the panel up until the attempt settles.
 */
export function useRetry(refetch: () => Promise<unknown>) {
  const [retrying, setRetrying] = useState(false);

  const retry = useCallback(async () => {
    setRetrying(true);
    try {
      await refetch();
    } finally {
      setRetrying(false);
    }
  }, [refetch]);

  return { retrying, retry };
}
