/**
 * TanStack Query hooks for the ads domain.
 *
 * The public list is short-cached (60s) so newly-published ads show up
 * quickly without thrashing the network on every nav. The owner-scoped
 * `my ads` list is even shorter (15s) because the user expects their actions
 * to take effect immediately. Detail uses a 60s stale window — short enough
 * to keep view counts fresh, long enough that the page doesn't re-fetch on
 * every back/forward navigation.
 *
 * Every mutation invalidates the keys it touches so list ⇄ detail stay
 * consistent without manual cache surgery in callers.
 */
import {
  useMutation,
  useQuery,
  useQueryClient,
  type UseMutationResult,
  type UseQueryResult,
} from '@tanstack/react-query';
import {
  createAd,
  deleteAd,
  getAd,
  getMyAds,
  listAds,
  markSold,
  publishAd,
  renewAd,
  updateAd,
  type ListAdsParams,
  type MyAdsParams,
} from '@/lib/api/ads';
import type { ApiClientError } from '@/lib/api/auth';
import type {
  Ad,
  AdSummary,
  CreateAdRequest,
  PaginatedResponse,
  UpdateAdRequest,
} from '@/lib/api/types';

const MINUTE = 60 * 1000;

export const adKeys = {
  all: ['ads'] as const,
  lists: () => [...adKeys.all, 'list'] as const,
  list: (params: ListAdsParams) => [...adKeys.lists(), params] as const,
  details: () => [...adKeys.all, 'detail'] as const,
  detail: (id: string) => [...adKeys.details(), id] as const,
  myLists: () => [...adKeys.all, 'mine'] as const,
  myList: (params: MyAdsParams) => [...adKeys.myLists(), params] as const,
};

// ── Queries ────────────────────────────────────────────────────────────────

export function useAdsListQuery(
  params: ListAdsParams = {},
): UseQueryResult<PaginatedResponse<AdSummary>, ApiClientError> {
  return useQuery({
    queryKey: adKeys.list(params),
    queryFn: () => listAds(params),
    staleTime: MINUTE,
    // The home feed paginates manually — keep the previous page visible
    // while the next page is in flight to avoid a flash of empty state.
    placeholderData: (prev) => prev,
  });
}

export function useAdQuery(
  id: string | null | undefined,
): UseQueryResult<Ad, ApiClientError> {
  return useQuery({
    queryKey: adKeys.detail(id ?? ''),
    queryFn: () => getAd(id as string),
    enabled: Boolean(id),
    staleTime: MINUTE,
  });
}

export function useMyAdsQuery(
  params: MyAdsParams = {},
): UseQueryResult<PaginatedResponse<AdSummary>, ApiClientError> {
  return useQuery({
    queryKey: adKeys.myList(params),
    queryFn: () => getMyAds(params),
    staleTime: 15 * 1000,
    placeholderData: (prev) => prev,
  });
}

// ── Mutations ──────────────────────────────────────────────────────────────

export function useCreateAdMutation(): UseMutationResult<
  Ad,
  ApiClientError,
  CreateAdRequest
> {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: CreateAdRequest) => createAd(payload),
    onSuccess: (ad) => {
      qc.setQueryData(adKeys.detail(ad.id), ad);
      qc.invalidateQueries({ queryKey: adKeys.myLists() });
    },
  });
}

export function useUpdateAdMutation(): UseMutationResult<
  Ad,
  ApiClientError,
  { id: string; payload: UpdateAdRequest }
> {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, payload }) => updateAd(id, payload),
    onSuccess: (ad) => {
      qc.setQueryData(adKeys.detail(ad.id), ad);
      qc.invalidateQueries({ queryKey: adKeys.lists() });
      qc.invalidateQueries({ queryKey: adKeys.myLists() });
    },
  });
}

export function useDeleteAdMutation(): UseMutationResult<
  void,
  ApiClientError,
  string
> {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => deleteAd(id),
    onSuccess: (_void, id) => {
      qc.removeQueries({ queryKey: adKeys.detail(id) });
      qc.invalidateQueries({ queryKey: adKeys.lists() });
      qc.invalidateQueries({ queryKey: adKeys.myLists() });
    },
  });
}

export function usePublishAdMutation(): UseMutationResult<
  Ad,
  ApiClientError,
  string
> {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => publishAd(id),
    onSuccess: (ad) => {
      qc.setQueryData(adKeys.detail(ad.id), ad);
      qc.invalidateQueries({ queryKey: adKeys.lists() });
      qc.invalidateQueries({ queryKey: adKeys.myLists() });
    },
  });
}

export function useMarkSoldMutation(): UseMutationResult<
  Ad,
  ApiClientError,
  string
> {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => markSold(id),
    onSuccess: (ad) => {
      qc.setQueryData(adKeys.detail(ad.id), ad);
      qc.invalidateQueries({ queryKey: adKeys.lists() });
      qc.invalidateQueries({ queryKey: adKeys.myLists() });
    },
  });
}

export function useRenewAdMutation(): UseMutationResult<
  Ad,
  ApiClientError,
  string
> {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => renewAd(id),
    onSuccess: (ad) => {
      qc.setQueryData(adKeys.detail(ad.id), ad);
      qc.invalidateQueries({ queryKey: adKeys.lists() });
      qc.invalidateQueries({ queryKey: adKeys.myLists() });
    },
  });
}
