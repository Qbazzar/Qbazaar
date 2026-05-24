/**
 * TanStack Query hooks for the messaging domain.
 *
 * Caching strategy:
 * - Conversations list: staleTime 30s; the WebSocket pushes invalidations
 *   when a new message arrives, so polling isn't needed.
 * - Single conversation: staleTime 30s — only fetched when the right pane
 *   opens.
 * - Messages: infinite query keyed by conversation id, paginated backwards
 *   via a `before` cursor (the oldest message's `created_at`).
 * - Unread count: 60s polling fallback so the badge still updates when the
 *   socket isn't connected.
 *
 * Mutations:
 * - `useStartConversationMutation` invalidates the list (a new row may have
 *   appeared) and seeds the conversation cache with the returned envelope.
 * - `useSendMessageMutation` is optimistic: it inserts a temporary message
 *   into the infinite cache and rolls back on error. On success the temp
 *   row is replaced with the real one so the timestamp/id reconcile.
 * - `useMarkReadMutation` invalidates the unread count + list previews.
 */
import { useEffect } from 'react';
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
import {
  getConversation,
  getMessages,
  getUnreadCount,
  listConversations,
  markRead,
  sendMessage,
  startConversation,
  type GetMessagesResponse,
  type ListConversationsParams,
} from '@/lib/api/messaging';
import type { ApiClientError } from '@/lib/api/auth';
import type {
  Conversation,
  ConversationListItem,
  Message,
  MessageType,
  PaginatedResponse,
  UnreadCountResponse,
} from '@/lib/api/types';
import { useAuthStore } from '@/store/auth';
import { useMessagingStore } from '@/store/messaging';

const SECOND = 1000;

const MESSAGES_PAGE_SIZE = 50;

export const messagingKeys = {
  all: ['messaging'] as const,
  lists: () => [...messagingKeys.all, 'list'] as const,
  list: (params: ListConversationsParams) =>
    [...messagingKeys.lists(), params] as const,
  details: () => [...messagingKeys.all, 'detail'] as const,
  detail: (id: string) => [...messagingKeys.details(), id] as const,
  messages: (conversationId: string) =>
    [...messagingKeys.all, 'messages', conversationId] as const,
  unread: () => [...messagingKeys.all, 'unread'] as const,
};

// ── Queries ────────────────────────────────────────────────────────────────

export function useConversationsQuery(
  params: ListConversationsParams = {},
): UseQueryResult<PaginatedResponse<ConversationListItem>, ApiClientError> {
  const isAuthenticated = useAuthStore((s) => Boolean(s.user && s.accessToken));
  return useQuery({
    queryKey: messagingKeys.list(params),
    queryFn: () => listConversations(params),
    enabled: isAuthenticated,
    staleTime: 30 * SECOND,
    placeholderData: (prev) => prev,
  });
}

export function useConversationQuery(
  id: string | null | undefined,
): UseQueryResult<Conversation, ApiClientError> {
  const isAuthenticated = useAuthStore((s) => Boolean(s.user && s.accessToken));
  return useQuery({
    queryKey: messagingKeys.detail(id ?? ''),
    queryFn: () => getConversation(id as string),
    enabled: isAuthenticated && Boolean(id),
    staleTime: 30 * SECOND,
  });
}

export function useMessagesQuery(
  conversationId: string | null | undefined,
): UseInfiniteQueryResult<InfiniteData<GetMessagesResponse>, ApiClientError> {
  const isAuthenticated = useAuthStore((s) => Boolean(s.user && s.accessToken));

  return useInfiniteQuery<
    GetMessagesResponse,
    ApiClientError,
    InfiniteData<GetMessagesResponse>,
    readonly unknown[],
    string | undefined
  >({
    queryKey: messagingKeys.messages(conversationId ?? ''),
    queryFn: ({ pageParam }) =>
      getMessages(conversationId as string, {
        before: pageParam,
        limit: MESSAGES_PAGE_SIZE,
      }),
    enabled: isAuthenticated && Boolean(conversationId),
    initialPageParam: undefined,
    getNextPageParam: (lastPage) => {
      if (!lastPage.meta.has_more) return undefined;
      const oldest = lastPage.data[lastPage.data.length - 1];
      return oldest?.created_at;
    },
    staleTime: 10 * SECOND,
  });
}

export function useUnreadCountQuery(): UseQueryResult<
  UnreadCountResponse,
  ApiClientError
> {
  const isAuthenticated = useAuthStore((s) => Boolean(s.user && s.accessToken));
  const setUnreadCount = useMessagingStore((s) => s.setUnreadCount);

  const result = useQuery<UnreadCountResponse, ApiClientError>({
    queryKey: messagingKeys.unread(),
    queryFn: getUnreadCount,
    enabled: isAuthenticated,
    staleTime: 30 * SECOND,
    // Polling is a fallback for when Reverb/WebSocket isn't connected.
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

export function useStartConversationMutation(): UseMutationResult<
  Conversation,
  ApiClientError,
  string
> {
  const qc = useQueryClient();
  return useMutation<Conversation, ApiClientError, string>({
    mutationFn: (adId) => startConversation(adId),
    onSuccess: (conversation) => {
      qc.setQueryData(messagingKeys.detail(conversation.id), conversation);
      qc.invalidateQueries({ queryKey: messagingKeys.lists() });
    },
  });
}

interface SendContext {
  tempId: string;
  conversationId: string;
}

interface SendVars {
  conversationId: string;
  body: string;
  type?: MessageType;
}

/**
 * Optimistically appends a temporary message into the infinite cache so the
 * UI feels instant. On success the real server payload replaces the temp
 * row; on error we roll back to the previous snapshot.
 */
export function useSendMessageMutation(): UseMutationResult<
  Message,
  ApiClientError,
  SendVars,
  SendContext
> {
  const qc = useQueryClient();
  const currentUser = useAuthStore((s) => s.user);

  return useMutation<Message, ApiClientError, SendVars, SendContext>({
    mutationFn: ({ conversationId, body, type }) =>
      sendMessage(conversationId, body, type),
    onMutate: async ({ conversationId, body, type = 'text' }) => {
      const queryKey = messagingKeys.messages(conversationId);
      await qc.cancelQueries({ queryKey });
      const tempId = `temp-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
      const tempMessage: Message = {
        id: tempId,
        conversation_id: conversationId,
        sender_id: currentUser?.id ?? '',
        body,
        type,
        read_at: null,
        created_at: new Date().toISOString(),
        sender: {
          id: currentUser?.id ?? '',
          full_name: currentUser?.full_name ?? '',
          avatar_thumb_url: currentUser?.avatar_thumb_url ?? null,
        },
      };

      qc.setQueryData<InfiniteData<GetMessagesResponse>>(queryKey, (prev) => {
        if (!prev) {
          return {
            pages: [{ data: [tempMessage], meta: { has_more: false } }],
            pageParams: [undefined],
          };
        }
        // The first page is the newest batch; prepend the temp message so
        // it appears at the bottom of the chat (we reverse for display).
        const pages = [...prev.pages];
        pages[0] = {
          ...pages[0],
          data: [tempMessage, ...pages[0].data],
        };
        return { ...prev, pages };
      });

      return { tempId, conversationId };
    },
    onError: (_err, _vars, context) => {
      if (!context) return;
      const queryKey = messagingKeys.messages(context.conversationId);
      qc.setQueryData<InfiniteData<GetMessagesResponse>>(queryKey, (prev) => {
        if (!prev) return prev;
        return {
          ...prev,
          pages: prev.pages.map((p) => ({
            ...p,
            data: p.data.filter((m) => m.id !== context.tempId),
          })),
        };
      });
    },
    onSuccess: (realMessage, _vars, context) => {
      if (!context) return;
      const queryKey = messagingKeys.messages(context.conversationId);
      qc.setQueryData<InfiniteData<GetMessagesResponse>>(queryKey, (prev) => {
        if (!prev) return prev;
        return {
          ...prev,
          pages: prev.pages.map((p) => ({
            ...p,
            data: p.data.map((m) => (m.id === context.tempId ? realMessage : m)),
          })),
        };
      });
      // Refresh list previews + unread counts.
      qc.invalidateQueries({ queryKey: messagingKeys.lists() });
    },
  });
}

export function useMarkReadMutation(): UseMutationResult<
  { marked: number },
  ApiClientError,
  string
> {
  const qc = useQueryClient();
  return useMutation<{ marked: number }, ApiClientError, string>({
    mutationFn: (conversationId) => markRead(conversationId),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: messagingKeys.unread() });
      qc.invalidateQueries({ queryKey: messagingKeys.lists() });
    },
  });
}

/**
 * Helper used by Echo callbacks to append an incoming real-time message
 * into the infinite cache. Idempotent: a message with the same id is left
 * untouched so we don't duplicate when both the WebSocket push AND the
 * optimistic mutation try to write the same row.
 */
export function appendIncomingMessageToCache(
  qc: ReturnType<typeof useQueryClient>,
  message: Message,
): void {
  const queryKey = messagingKeys.messages(message.conversation_id);
  qc.setQueryData<InfiniteData<GetMessagesResponse>>(queryKey, (prev) => {
    if (!prev) return prev;
    // If we already have it (either from optimistic send or a duplicate
    // push), skip the insert.
    const exists = prev.pages.some((p) =>
      p.data.some((m) => m.id === message.id),
    );
    if (exists) return prev;
    const pages = [...prev.pages];
    pages[0] = {
      ...pages[0],
      data: [message, ...pages[0].data],
    };
    return { ...prev, pages };
  });
}
