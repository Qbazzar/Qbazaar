/**
 * TanStack Query hooks for the recently-viewed domain.
 *
 * Tracking is fire-and-forget: failures are swallowed so a transient network
 * blip doesn't bubble up to the ad-detail page. The list query is short-cached
 * (60s) because the user expects "I just viewed this" to surface on the home
 * strip immediately. Clear invalidates the list + resets to an empty page.
 */
import {
  useMutation,
  useQuery,
  useQueryClient,
  type UseMutationResult,
  type UseQueryResult,
} from '@tanstack/react-query';
import {
  clearRecentlyViewed,
  listRecentlyViewed,
  trackView,
  type ListRecentlyViewedParams,
} from '@/lib/api/recently-viewed';
import type { ApiClientError } from '@/lib/api/auth';
import type {
  PaginatedResponse,
  RecentlyViewedAdSummary,
} from '@/lib/api/types';

const SECOND = 1000;
const MINUTE = 60 * SECOND;

export const recentlyViewedKeys = {
  all: ['recently-viewed'] as const,
  lists: () => [...recentlyViewedKeys.all, 'list'] as const,
  list: (params: ListRecentlyViewedParams) =>
    [...recentlyViewedKeys.lists(), params] as const,
};

// ── Queries ────────────────────────────────────────────────────────────────

export function useRecentlyViewedQuery(
  params: ListRecentlyViewedParams = {},
  options: { enabled?: boolean } = {},
): UseQueryResult<PaginatedResponse<RecentlyViewedAdSummary>, ApiClientError> {
  return useQuery({
    queryKey: recentlyViewedKeys.list(params),
    queryFn: () => listRecentlyViewed(params),
    staleTime: MINUTE,
    enabled: options.enabled ?? true,
    placeholderData: (prev) => prev,
  });
}

// ── Mutations ──────────────────────────────────────────────────────────────

/**
 * Silent view tracker. Errors are swallowed inside the hook so callers can
 * fire-and-forget without a try/catch — a missed tracking call is never
 * user-visible.
 */
export function useTrackAdViewMutation(): UseMutationResult<
  void,
  ApiClientError,
  string
> {
  const qc = useQueryClient();
  return useMutation<void, ApiClientError, string>({
    mutationFn: (adId: string) => trackView(adId),
    onSuccess: () => {
      // Refresh the recently-viewed list so the just-viewed ad appears at
      // the top of the home strip next time it loads.
      qc.invalidateQueries({ queryKey: recentlyViewedKeys.lists() });
    },
    // Errors swallowed by design — tracking is best-effort.
    onError: () => undefined,
  });
}

export function useClearRecentlyViewedMutation(): UseMutationResult<
  void,
  ApiClientError,
  void
> {
  const qc = useQueryClient();
  return useMutation<void, ApiClientError, void>({
    mutationFn: () => clearRecentlyViewed(),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: recentlyViewedKeys.lists() });
    },
  });
}
