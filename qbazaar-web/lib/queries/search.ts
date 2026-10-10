/**
 * TanStack Query hooks for the search domain.
 *
 * Three flavours of caching:
 *
 * - Search results: 30s staleTime so freshly published ads surface quickly
 *   without thrashing the network as the user tweaks filters.
 * - Suggestions: 5m staleTime, gated on `q.length >= 2` so the type-ahead
 *   doesn't fire on every key.
 * - Saved searches: 5m staleTime. The list is small and rarely changes; the
 *   mutations invalidate it surgically.
 */
import {
  useMutation,
  useQuery,
  useQueryClient,
  type UseMutationResult,
  type UseQueryResult,
} from '@tanstack/react-query';
import {
  createSavedSearch,
  deleteSavedSearch,
  getSuggestions,
  listSavedSearches,
  runSearch,
  setSavedSearchAlerts,
  type CreateSavedSearchPayload,
} from '@/lib/api/search';
import type { ApiClientError } from '@/lib/api/auth';
import type {
  SavedSearch,
  SearchQueryParams,
  SearchResponse,
  SearchSuggestion,
} from '@/lib/api/types';

const SECOND = 1000;
const MINUTE = 60 * SECOND;

export const searchKeys = {
  all: ['search'] as const,
  results: () => [...searchKeys.all, 'results'] as const,
  result: (params: SearchQueryParams) =>
    [...searchKeys.results(), params] as const,
  suggestions: () => [...searchKeys.all, 'suggestions'] as const,
  suggestion: (q: string) => [...searchKeys.suggestions(), q] as const,
  saved: () => [...searchKeys.all, 'saved'] as const,
};

// ── Queries ────────────────────────────────────────────────────────────────

export function useSearchQuery(
  params: SearchQueryParams,
): UseQueryResult<SearchResponse, ApiClientError> {
  return useQuery({
    queryKey: searchKeys.result(params),
    queryFn: () => runSearch(params),
    staleTime: 30 * SECOND,
    // Keep the previous page on screen while the next page loads — otherwise
    // the grid flashes an empty state on every filter change.
    placeholderData: (prev) => prev,
  });
}

export function useSearchSuggestionsQuery(
  q: string,
): UseQueryResult<SearchSuggestion[], ApiClientError> {
  const trimmed = q.trim();
  return useQuery({
    queryKey: searchKeys.suggestion(trimmed),
    queryFn: () => getSuggestions(trimmed),
    enabled: trimmed.length >= 2,
    staleTime: 5 * MINUTE,
  });
}

export function useSavedSearchesQuery(
  enabled: boolean = true,
): UseQueryResult<SavedSearch[], ApiClientError> {
  return useQuery({
    queryKey: searchKeys.saved(),
    queryFn: listSavedSearches,
    enabled,
    staleTime: 5 * MINUTE,
  });
}

// ── Mutations ──────────────────────────────────────────────────────────────

export function useSaveSearchMutation(): UseMutationResult<
  SavedSearch,
  ApiClientError,
  CreateSavedSearchPayload
> {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: createSavedSearch,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: searchKeys.saved() });
    },
  });
}

export function useDeleteSavedSearchMutation(): UseMutationResult<
  void,
  ApiClientError,
  string
> {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: deleteSavedSearch,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: searchKeys.saved() });
    },
  });
}

/**
 * "Clear all" and "Clean up". The API has no bulk call and an account keeps
 * at most ten saved searches, so the ids go out in parallel. Resolves with
 * how many requests failed.
 */
export function useDeleteSavedSearchesMutation(): UseMutationResult<number, ApiClientError, readonly string[]> {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (ids) => {
      const results = await Promise.allSettled(ids.map((id) => deleteSavedSearch(id)));
      return results.filter((result) => result.status === 'rejected').length;
    },
    onSettled: () => {
      qc.invalidateQueries({ queryKey: searchKeys.saved() });
    },
  });
}

/** "Notification on / off" of a saved search: flips at once and rolls back if the request fails. */
export function useSavedSearchAlertsMutation(): UseMutationResult<
  SavedSearch,
  ApiClientError,
  { id: string; alertsEnabled: boolean },
  { previous?: SavedSearch[] }
> {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, alertsEnabled }) => setSavedSearchAlerts(id, alertsEnabled),
    onMutate: async ({ id, alertsEnabled }) => {
      await qc.cancelQueries({ queryKey: searchKeys.saved() });
      const previous = qc.getQueryData<SavedSearch[]>(searchKeys.saved());
      qc.setQueryData<SavedSearch[]>(searchKeys.saved(), (rows) =>
        rows?.map((row) => (row.id === id ? { ...row, alerts_enabled: alertsEnabled } : row)),
      );
      return { previous };
    },
    onError: (_err, _vars, context) => {
      if (context?.previous) qc.setQueryData(searchKeys.saved(), context.previous);
    },
    onSuccess: (saved) => {
      qc.setQueryData<SavedSearch[]>(searchKeys.saved(), (rows) =>
        rows?.map((row) => (row.id === saved.id ? saved : row)),
      );
    },
  });
}
