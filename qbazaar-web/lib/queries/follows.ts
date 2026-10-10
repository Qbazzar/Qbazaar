/**
 * TanStack Query hooks of the Following / Followers page. A follow toggled
 * on a row writes its new state into both lists, so the row and the other
 * tab agree without a refetch.
 */
import { useInfiniteQuery, useQueryClient, type InfiniteData } from '@tanstack/react-query';

import { useIsAuthenticated } from '@/hooks/useIsAuthenticated';
import { listFollows, type FollowListKind, type FollowListUser } from '@/lib/api/follows';
import type { CursorPage } from '@/lib/api/commerce-types';

const MINUTE = 60 * 1000;

export const followKeys = {
  all: ['account', 'follows'] as const,
  list: (kind: FollowListKind) => [...followKeys.all, kind] as const,
};

export function useFollowListQuery(kind: FollowListKind) {
  const enabled = useIsAuthenticated();
  return useInfiniteQuery({
    queryKey: followKeys.list(kind),
    queryFn: ({ pageParam }) => listFollows(kind, pageParam),
    initialPageParam: null as string | null,
    getNextPageParam: (last) => (last.meta.has_more ? last.meta.next_cursor : undefined),
    enabled,
    staleTime: MINUTE,
  });
}

/** Writes a row's new follow state into every cached page of both lists. */
export function useSetListedFollowState(): (userId: string, following: boolean) => void {
  const queryClient = useQueryClient();
  return (userId, following) => {
    queryClient.setQueriesData<InfiniteData<CursorPage<FollowListUser>>>({ queryKey: followKeys.all }, (data) =>
      data
        ? {
            ...data,
            pages: data.pages.map((page) => ({
              ...page,
              data: page.data.map((row) => (row.id === userId ? { ...row, is_following: following } : row)),
            })),
          }
        : data,
    );
  };
}
