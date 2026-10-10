/**
 * TanStack Query hooks for the favorites domain.
 *
 * The favorites list is medium-cached (60s) — it doesn't churn fast and the
 * mutation invalidates it surgically. The toggle mutation uses an
 * optimistic-update pattern so the heart icon flips instantly while the
 * request flies.
 */
import { useEffect } from 'react';
import {
  useMutation,
  useQuery,
  useQueryClient,
  type UseMutationResult,
  type UseQueryResult,
} from '@tanstack/react-query';
import {
  listFavoriteIds,
  listFavorites,
  removeFavorite,
  toggleFavorite,
  type ListFavoritesParams,
} from '@/lib/api/favorites';
import type { ApiClientError } from '@/lib/api/auth';
import type {
  Ad,
  FavoriteToggleResponse,
  FavoritedAdSummary,
  PaginatedResponse,
} from '@/lib/api/types';
import { useFavoritesStore } from '@/store/favorites';
import { useAuthStore } from '@/store/auth';

import { adKeys } from './ads';

const SECOND = 1000;
const MINUTE = 60 * SECOND;

export const favoritesKeys = {
  all: ['favorites'] as const,
  lists: () => [...favoritesKeys.all, 'list'] as const,
  list: (params: ListFavoritesParams) =>
    [...favoritesKeys.lists(), params] as const,
};

// ── Queries ────────────────────────────────────────────────────────────────

/**
 * Authenticated favorites list. Disabled when the user isn't signed in so we
 * don't waste a request that would 401 anyway.
 *
 * Side-effect: on every successful page-1 response we dump the ids into the
 * favorites store, keeping the heart icons on AdCards in sync with the
 * server's source of truth.
 */
export function useFavoritesQuery(
  params: ListFavoritesParams = {},
): UseQueryResult<PaginatedResponse<FavoritedAdSummary>, ApiClientError> {
  const isAuthenticated = useAuthStore((s) => Boolean(s.user && s.accessToken));
  const mergeIds = useFavoritesStore((s) => s.mergeIds);

  const result = useQuery<
    PaginatedResponse<FavoritedAdSummary>,
    ApiClientError
  >({
    queryKey: favoritesKeys.list(params),
    queryFn: () => listFavorites(params),
    enabled: isAuthenticated,
    staleTime: MINUTE,
    placeholderData: (prev) => prev,
  });

  // Sync server truth into the local store so AdCard hearts stay accurate.
  // Always merge: a page holds only part of the saved set, so replacing would
  // drop the hearts further down. Removals reach the store through the toggle.
  useEffect(() => {
    if (!result.data) return;
    const ids = result.data.data.map((ad) => ad.id);
    mergeIds(ids);
  }, [result.data, mergeIds]);

  return result;
}

// ── Mutations ──────────────────────────────────────────────────────────────

interface ToggleContext {
  previouslyFavorited: boolean;
}

/**
 * Optimistic favorite toggle.
 *
 * 1. Snapshot the local state for rollback.
 * 2. Flip the local store immediately so the heart animates.
 * 3. On success, reconcile with the server's authoritative response.
 * 4. On error, roll back to the snapshotted state.
 * 5. On settle, invalidate the favorites list so the account page reloads.
 */
export function useToggleFavoriteMutation(): UseMutationResult<
  FavoriteToggleResponse,
  ApiClientError,
  string,
  ToggleContext
> {
  const qc = useQueryClient();
  const toggleLocal = useFavoritesStore((s) => s.toggleLocal);
  const setOne = useFavoritesStore((s) => s.setOne);

  return useMutation<FavoriteToggleResponse, ApiClientError, string, ToggleContext>({
    mutationFn: (adId: string) => toggleFavorite(adId),
    onMutate: (adId) => {
      const previouslyFavorited = useFavoritesStore.getState().ids.has(adId);
      toggleLocal(adId);
      return { previouslyFavorited };
    },
    onError: (_err, adId, context) => {
      if (context) setOne(adId, context.previouslyFavorited);
    },
    onSuccess: (response, adId) => {
      // Reconcile in case the server disagrees with our optimistic flip.
      setOne(adId, response.favorited);
      // A cached ad page lays its own flag over the store when it mounts again.
      qc.setQueryData<Ad>(adKeys.detail(adId), (ad) => (ad ? { ...ad, is_favorited: response.favorited } : ad));
    },
    onSettled: () => {
      qc.invalidateQueries({ queryKey: favoritesKeys.lists() });
    },
  });
}

/** Removals sent at once while clearing; keeps the burst well under the API's rate limit. */
const CLEAR_BATCH_SIZE = 4;

/**
 * "Clear all" of the wishlist. The API has no bulk delete, so every saved
 * ad is removed on its own, a few at a time. The hearts and the list are
 * refreshed afterwards whether or not every removal went through.
 */
export function useClearFavoritesMutation(): UseMutationResult<void, ApiClientError, void> {
  const qc = useQueryClient();
  const setIds = useFavoritesStore((s) => s.setIds);

  return useMutation<void, ApiClientError, void>({
    mutationFn: async () => {
      const ids = await listFavoriteIds();
      for (let start = 0; start < ids.length; start += CLEAR_BATCH_SIZE) {
        await Promise.all(ids.slice(start, start + CLEAR_BATCH_SIZE).map(removeFavorite));
      }
    },
    onSuccess: () => {
      // Keeps the ads marked as known, so a wishlist card empties its heart at once.
      setIds([]);
      qc.setQueriesData<Ad>({ queryKey: adKeys.details() }, (ad) => (ad ? { ...ad, is_favorited: false } : ad));
    },
    onSettled: () => {
      qc.invalidateQueries({ queryKey: favoritesKeys.all });
    },
  });
}

/**
 * Lays an ad's saved state, as the API returned it for the viewer, over the
 * hearts' store: a saved ad then shows its filled heart however the page was
 * reached (a refresh, a shared link), not only after the wishlist loaded.
 * `favorited` stays undefined while the copy at hand is not the viewer's own
 * (the anonymous server render).
 */
export function useSyncAdFavorite(adId: string | undefined, favorited: boolean | undefined): void {
  const setOne = useFavoritesStore((s) => s.setOne);

  useEffect(() => {
    if (adId && favorited !== undefined) setOne(adId, favorited);
  }, [adId, favorited, setOne]);
}
