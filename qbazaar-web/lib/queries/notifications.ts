/**
 * TanStack Query hooks for the notifications domain (Sprint 10).
 *
 * Caching strategy:
 * - List: staleTime 30s. The WebSocket pushes invalidations when a new
 *   notification arrives, so polling isn't needed.
 * - Unread count: staleTime 30s + 60s polling fallback so the bell still
 *   updates when the socket isn't connected.
 *
 * Mutations:
 * - `useMarkNotificationReadMutation` optimistically flips `read_at` in every
 *   cached page and decrements the unread count, then invalidates the count
 *   query on settle so the server stays canonical.
 * - `useMarkAllNotificationsReadMutation` zeroes the count + flips every
 *   row, then invalidates the list to refresh.
 * - `useDeleteNotificationMutation` drops the row from every cached page.
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
  deleteNotification,
  getUnreadNotificationsCount,
  listNotifications,
  markAllNotificationsRead,
  markNotificationRead,
  type ListNotificationsParams,
  type MarkAllReadResponse,
  type UnreadNotificationsCountResponse,
} from '@/lib/api/notifications';
import type { ApiClientError } from '@/lib/api/auth';
import type { Notification, PaginatedResponse } from '@/lib/api/types';
import { useAuthStore } from '@/store/auth';
import { useNotificationsStore } from '@/store/notifications';

const SECOND = 1000;

export const notificationsKeys = {
  all: ['notifications'] as const,
  lists: () => [...notificationsKeys.all, 'list'] as const,
  list: (params: ListNotificationsParams) =>
    [...notificationsKeys.lists(), params] as const,
  unread: () => [...notificationsKeys.all, 'unread'] as const,
};

// ── Queries ────────────────────────────────────────────────────────────────

export function useNotificationsQuery(
  params: ListNotificationsParams = {},
): UseQueryResult<PaginatedResponse<Notification>, ApiClientError> {
  const isAuthenticated = useAuthStore((s) => Boolean(s.user && s.accessToken));
  return useQuery({
    queryKey: notificationsKeys.list(params),
    queryFn: () => listNotifications(params),
    enabled: isAuthenticated,
    staleTime: 30 * SECOND,
    placeholderData: (prev) => prev,
  });
}

export function useUnreadNotificationsCountQuery(): UseQueryResult<
  UnreadNotificationsCountResponse,
  ApiClientError
> {
  const isAuthenticated = useAuthStore((s) => Boolean(s.user && s.accessToken));
  const setUnreadCount = useNotificationsStore((s) => s.setUnreadCount);

  const result = useQuery<UnreadNotificationsCountResponse, ApiClientError>({
    queryKey: notificationsKeys.unread(),
    queryFn: getUnreadNotificationsCount,
    enabled: isAuthenticated,
    staleTime: 30 * SECOND,
    refetchInterval: 60 * SECOND,
    refetchIntervalInBackground: false,
  });

  // Mirror the server-side total into the Zustand store so cheap badge
  // consumers can read it without subscribing to the query cache.
  useEffect(() => {
    if (typeof result.data?.total === 'number') {
      setUnreadCount(result.data.total);
    }
  }, [result.data, setUnreadCount]);

  return result;
}

// ── Mutations ──────────────────────────────────────────────────────────────

interface OptimisticReadContext {
  previous: Array<{
    queryKey: readonly unknown[];
    data: PaginatedResponse<Notification> | undefined;
  }>;
  previousUnread: UnreadNotificationsCountResponse | undefined;
}

/**
 * Walks every cached notifications-list page and applies a transform to the
 * `data` array, returning a snapshot the caller can use to roll back on
 * error. Centralised here so each mutation stays small + reads clearly.
 */
function patchAllListPages(
  qc: ReturnType<typeof useQueryClient>,
  transform: (rows: Notification[]) => Notification[],
): OptimisticReadContext['previous'] {
  const queries = qc.getQueriesData<PaginatedResponse<Notification>>({
    queryKey: notificationsKeys.lists(),
  });
  const previous: OptimisticReadContext['previous'] = [];
  for (const [queryKey, data] of queries) {
    previous.push({ queryKey, data });
    if (!data) continue;
    qc.setQueryData<PaginatedResponse<Notification>>(queryKey, {
      ...data,
      data: transform(data.data),
    });
  }
  return previous;
}

export function useMarkNotificationReadMutation(): UseMutationResult<
  Notification,
  ApiClientError,
  string,
  OptimisticReadContext
> {
  const qc = useQueryClient();
  return useMutation<Notification, ApiClientError, string, OptimisticReadContext>({
    mutationFn: (id) => markNotificationRead(id),
    onMutate: async (id) => {
      await qc.cancelQueries({ queryKey: notificationsKeys.all });

      const now = new Date().toISOString();
      const previous = patchAllListPages(qc, (rows) =>
        rows.map((row) =>
          row.id === id && !row.read_at ? { ...row, read_at: now } : row,
        ),
      );

      const previousUnread = qc.getQueryData<UnreadNotificationsCountResponse>(
        notificationsKeys.unread(),
      );
      if (previousUnread && previousUnread.total > 0) {
        qc.setQueryData<UnreadNotificationsCountResponse>(
          notificationsKeys.unread(),
          { total: Math.max(0, previousUnread.total - 1) },
        );
      }

      return { previous, previousUnread };
    },
    onError: (_err, _vars, context) => {
      if (!context) return;
      for (const snapshot of context.previous) {
        qc.setQueryData(snapshot.queryKey, snapshot.data);
      }
      if (context.previousUnread) {
        qc.setQueryData(notificationsKeys.unread(), context.previousUnread);
      }
    },
    onSettled: () => {
      qc.invalidateQueries({ queryKey: notificationsKeys.unread() });
    },
  });
}

export function useMarkAllNotificationsReadMutation(): UseMutationResult<
  MarkAllReadResponse,
  ApiClientError,
  void,
  OptimisticReadContext
> {
  const qc = useQueryClient();
  return useMutation<MarkAllReadResponse, ApiClientError, void, OptimisticReadContext>({
    mutationFn: () => markAllNotificationsRead(),
    onMutate: async () => {
      await qc.cancelQueries({ queryKey: notificationsKeys.all });

      const now = new Date().toISOString();
      const previous = patchAllListPages(qc, (rows) =>
        rows.map((row) => (row.read_at ? row : { ...row, read_at: now })),
      );

      const previousUnread = qc.getQueryData<UnreadNotificationsCountResponse>(
        notificationsKeys.unread(),
      );
      qc.setQueryData<UnreadNotificationsCountResponse>(
        notificationsKeys.unread(),
        { total: 0 },
      );

      return { previous, previousUnread };
    },
    onError: (_err, _vars, context) => {
      if (!context) return;
      for (const snapshot of context.previous) {
        qc.setQueryData(snapshot.queryKey, snapshot.data);
      }
      if (context.previousUnread) {
        qc.setQueryData(notificationsKeys.unread(), context.previousUnread);
      }
    },
    onSettled: () => {
      qc.invalidateQueries({ queryKey: notificationsKeys.all });
    },
  });
}

export function useDeleteNotificationMutation(): UseMutationResult<
  void,
  ApiClientError,
  string,
  OptimisticReadContext
> {
  const qc = useQueryClient();
  return useMutation<void, ApiClientError, string, OptimisticReadContext>({
    mutationFn: (id) => deleteNotification(id),
    onMutate: async (id) => {
      await qc.cancelQueries({ queryKey: notificationsKeys.all });

      // Snapshot rows-being-removed so we know whether to decrement unread.
      let removedUnreadCount = 0;
      const previous = patchAllListPages(qc, (rows) => {
        return rows.filter((row) => {
          if (row.id === id) {
            if (!row.read_at) removedUnreadCount += 1;
            return false;
          }
          return true;
        });
      });

      const previousUnread = qc.getQueryData<UnreadNotificationsCountResponse>(
        notificationsKeys.unread(),
      );
      // The same row can appear in multiple cached list pages (e.g. "all" +
      // "unread" tabs); clamp the decrement to 1 to mirror server reality.
      if (previousUnread && removedUnreadCount > 0) {
        qc.setQueryData<UnreadNotificationsCountResponse>(
          notificationsKeys.unread(),
          { total: Math.max(0, previousUnread.total - 1) },
        );
      }

      return { previous, previousUnread };
    },
    onError: (_err, _vars, context) => {
      if (!context) return;
      for (const snapshot of context.previous) {
        qc.setQueryData(snapshot.queryKey, snapshot.data);
      }
      if (context.previousUnread) {
        qc.setQueryData(notificationsKeys.unread(), context.previousUnread);
      }
    },
    onSettled: () => {
      qc.invalidateQueries({ queryKey: notificationsKeys.all });
    },
  });
}

/**
 * "Read All" / "Delete" on the selected rows. The API has no bulk call, so
 * the ids (at most one page) go out in parallel; the lists and the count are
 * refetched afterwards. Resolves with how many requests failed.
 */
export function useBulkNotificationsMutation(): UseMutationResult<
  number,
  ApiClientError,
  { action: 'read' | 'delete'; ids: readonly string[] }
> {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async ({ action, ids }) => {
      const run = action === 'read' ? markNotificationRead : deleteNotification;
      const results = await Promise.allSettled(ids.map((id) => run(id)));
      return results.filter((result) => result.status === 'rejected').length;
    },
    onSettled: () => {
      qc.invalidateQueries({ queryKey: notificationsKeys.all });
    },
  });
}
