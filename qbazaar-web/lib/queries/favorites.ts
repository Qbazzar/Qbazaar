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
  listFavorites,
  toggleFavorite,
  type ListFavoritesParams,
} from '@/lib/api/favorites';
import type { ApiClientError } from '@/lib/api/auth';
import type {
  FavoriteToggleResponse,
  FavoritedAdSummary,
  PaginatedResponse,
} from '@/lib/api/types';
import { useFavoritesStore } from '@/store/favorites';
import { useAuthStore } from '@/store/auth';

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
  const setIds = useFavoritesStore((s) => s.setIds);

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
  // Page 1 = reset; subsequent pages = merge so we don't drop already-known ids.
  useEffect(() => {
    if (!result.data) return;
    const ids = result.data.data.map((ad) => ad.id);
    if ((params.page ?? 1) <= 1) setIds(ids);
    else mergeIds(ids);
  }, [result.data, params.page, mergeIds, setIds]);

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
    },
    onSettled: () => {
      qc.invalidateQueries({ queryKey: favoritesKeys.lists() });
    },
  });
}
