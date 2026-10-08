/**
 * TanStack Query hook for the Qatar location tree.
 *
 * Locations almost never change, so we cache for 24h on the client. The
 * fetched tree is also mirrored into the Zustand store for downstream
 * lookups (e.g. resolving a slug back to a label inside breadcrumb chips).
 */
import { useQuery, type UseQueryResult } from '@tanstack/react-query';
import { useEffect } from 'react';
import { getQatarLocations } from '@/lib/api/locations';
import { useLocationsStore } from '@/store/locations';
import type { Location } from '@/lib/api/types';

const DAY = 24 * 60 * 60 * 1000;

export const locationKeys = {
  all: ['locations'] as const,
  qatar: () => [...locationKeys.all, 'qatar'] as const,
};

/** `initialPlaces` is the tree the page fetched on the server; it counts as fresh. */
export function useQatarLocationsQuery(initialPlaces?: Location[]): UseQueryResult<Location[]> {
  const setQatar = useLocationsStore((s) => s.setQatar);
  const query = useQuery({
    queryKey: locationKeys.qatar(),
    queryFn: getQatarLocations,
    staleTime: DAY,
    initialData: initialPlaces,
  });

  useEffect(() => {
    if (query.data) setQatar(query.data);
  }, [query.data, setQatar]);

  return query;
}
