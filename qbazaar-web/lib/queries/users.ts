/**
 * TanStack Query hooks for public user profiles: the profile itself, the
 * seller's active ads, their reviews and the follow toggle.
 *
 * The keys stay under `['users', id, ...]` so invalidating `['users', id]`
 * (as the review dialog does) refreshes everything about that user.
 */
import {
  useInfiniteQuery,
  useMutation,
  useQuery,
  useQueryClient,
  type InfiniteData,
  type UseInfiniteQueryResult,
  type UseMutationResult,
  type UseQueryResult,
} from '@tanstack/react-query';

import { ApiClientError } from '@/lib/api/auth';
import {
  followUser,
  getPublicProfile,
  getUserAds,
  getUserReviews,
  unfollowUser,
} from '@/lib/api/users';
import type {
  AdSummary,
  FollowState,
  PaginatedEnvelope,
  PublicUserProfile,
  Review,
} from '@/lib/api/types';
import { useAuthStore } from '@/store/auth';

const MINUTE = 60 * 1000;
const ADS_PER_PAGE = 20;

export const userKeys = {
  all: ['users'] as const,
  detail: (id: string) => [...userKeys.all, id] as const,
  profiles: (id: string) => [...userKeys.detail(id), 'public-profile'] as const,
  /** The profile depends on who is asking (`is_following`), so the viewer is part of the key. */
  profile: (id: string, viewerId: string | null) => [...userKeys.profiles(id), viewerId ?? 'guest'] as const,
  ads: (id: string) => [...userKeys.detail(id), 'ads'] as const,
  reviews: (id: string) => [...userKeys.detail(id), 'reviews'] as const,
};

/**
 * Public profile. `initialData` is the anonymous copy the page fetched on the
 * server: it renders at once, and the hook refetches on mount (with the
 * viewer's token, once the session is known) so counts and `is_following`
 * are fresh.
 */
export function usePublicProfileQuery(
  id: string,
  initialData?: PublicUserProfile,
): UseQueryResult<PublicUserProfile, ApiClientError> {
  const viewerId = useAuthStore((s) => (s.user && s.accessToken ? s.user.id : null));
  const isHydrated = useAuthStore((s) => s.isHydrated);

  return useQuery({
    queryKey: userKeys.profile(id, viewerId),
    queryFn: () => getPublicProfile(id),
    enabled: isHydrated,
    initialData,
    initialDataUpdatedAt: 0,
    staleTime: MINUTE,
    retry: (failureCount, err) => {
      // A 404 means the user doesn't exist; retrying won't change that.
      if (err instanceof ApiClientError && err.status === 404) return false;
      return failureCount < 2;
    },
  });
}

/** The seller's active ads, newest first, one page of 20 at a time ("load more"). */
export function useUserAdsQuery(
  id: string,
): UseInfiniteQueryResult<InfiniteData<PaginatedEnvelope<AdSummary>, number>, ApiClientError> {
  return useInfiniteQuery({
    queryKey: userKeys.ads(id),
    queryFn: ({ pageParam }) => getUserAds(id, { page: pageParam, per_page: ADS_PER_PAGE }),
    initialPageParam: 1,
    getNextPageParam: ({ meta }) => (meta.current_page < meta.last_page ? meta.current_page + 1 : undefined),
    staleTime: MINUTE,
  });
}

/** First page of the seller's reviews; skipped while they have none. */
export function useUserReviewsQuery(
  id: string,
  enabled: boolean,
): UseQueryResult<PaginatedEnvelope<Review>, ApiClientError> {
  return useQuery({
    queryKey: userKeys.reviews(id),
    queryFn: () => getUserReviews(id),
    enabled,
    staleTime: 5 * MINUTE,
  });
}

/**
 * Follow (`true`) or unfollow (`false`). The server is idempotent and returns
 * the new state, which is written into every cached copy of the profile.
 */
export function useFollowMutation(
  userId: string,
): UseMutationResult<FollowState, ApiClientError, boolean> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (follow: boolean) => (follow ? followUser(userId) : unfollowUser(userId)),
    onSuccess: (state) => {
      queryClient.setQueriesData<PublicUserProfile>({ queryKey: userKeys.profiles(userId) }, (profile) =>
        profile
          ? { ...profile, is_following: state.following, followers_count: state.followers_count }
          : profile,
      );
    },
  });
}
