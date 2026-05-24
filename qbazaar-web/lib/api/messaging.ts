/**
 * Typed client for the messaging endpoints (Sprint 8).
 *
 * Mirrors `qbazaar-contracts/openapi/v1.yaml` (BE-8.x) and routes everything
 * through the shared axios instance so cookies, locale and the 401-refresh
 * dance are inherited. All errors are normalised into `ApiClientError` so
 * the UI can switch on stable `MessagingErrorCode` values.
 */
import { isAxiosError } from 'axios';
import { api } from './client';
import { ApiClientError } from './auth';
import type {
  Conversation,
  ConversationListItem,
  ErrorEnvelope,
  Message,
  MessageType,
  PaginatedResponse,
  SuccessEnvelope,
  UnreadCountResponse,
} from './types';

const CONVERSATIONS_BASE = '/api/v1/conversations';

function toApiClientError(err: unknown): ApiClientError {
  if (isAxiosError<ErrorEnvelope>(err) && err.response?.data?.error) {
    const e = err.response.data.error;
    return new ApiClientError({
      status: err.response.status,
      code: e.code,
      messageKey: e.message_key,
      message: e.message,
      details: e.details,
      requestId: e.request_id,
    });
  }
  if (err instanceof Error) {
    return new ApiClientError({
      status: 0,
      code: 'NETWORK_ERROR',
      messageKey: 'errors.network',
      message: err.message,
    });
  }
  return new ApiClientError({
    status: 0,
    code: 'UNKNOWN_ERROR',
    messageKey: 'errors.unknown',
    message: 'Unknown error',
  });
}

function cleanParams<T extends Record<string, unknown>>(params: T): Partial<T> {
  const out: Partial<T> = {};
  for (const [k, v] of Object.entries(params)) {
    if (v === undefined || v === null || v === '') continue;
    (out as Record<string, unknown>)[k] = v;
  }
  return out;
}

export interface ListConversationsParams extends Record<string, unknown> {
  page?: number;
  per_page?: number;
}

export interface GetMessagesParams extends Record<string, unknown> {
  /** Cursor — `created_at` of the oldest message currently visible. */
  before?: string;
  limit?: number;
}

export interface GetMessagesResponse {
  data: Message[];
  meta: { has_more: boolean };
}

/**
 * Start (or rejoin) a conversation with the owner of `adId`. The backend
 * returns 201 for a brand-new thread or 200 when one already exists — both
 * payloads use the same `Conversation` envelope so callers don't have to
 * branch on status.
 */
export async function startConversation(adId: string): Promise<Conversation> {
  try {
    const { data } = await api.post<SuccessEnvelope<Conversation>>(
      CONVERSATIONS_BASE,
      { ad_id: adId },
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function listConversations(
  params: ListConversationsParams = {},
): Promise<PaginatedResponse<ConversationListItem>> {
  try {
    const { data } = await api.get<PaginatedResponse<ConversationListItem>>(
      CONVERSATIONS_BASE,
      { params: cleanParams(params) },
    );
    return data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function getConversation(id: string): Promise<Conversation> {
  try {
    const { data } = await api.get<SuccessEnvelope<Conversation>>(
      `${CONVERSATIONS_BASE}/${encodeURIComponent(id)}`,
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function getMessages(
  conversationId: string,
  params: GetMessagesParams = {},
): Promise<GetMessagesResponse> {
  try {
    const { data } = await api.get<GetMessagesResponse>(
      `${CONVERSATIONS_BASE}/${encodeURIComponent(conversationId)}/messages`,
      { params: cleanParams(params) },
    );
    return data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function sendMessage(
  conversationId: string,
  body: string,
  type: MessageType = 'text',
): Promise<Message> {
  try {
    const { data } = await api.post<SuccessEnvelope<Message>>(
      `${CONVERSATIONS_BASE}/${encodeURIComponent(conversationId)}/messages`,
      { body, type },
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function markRead(
  conversationId: string,
): Promise<{ marked: number }> {
  try {
    const { data } = await api.post<SuccessEnvelope<{ marked: number }>>(
      `${CONVERSATIONS_BASE}/${encodeURIComponent(conversationId)}/read`,
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function getUnreadCount(): Promise<UnreadCountResponse> {
  try {
    const { data } = await api.get<SuccessEnvelope<UnreadCountResponse>>(
      `${CONVERSATIONS_BASE}/unread-count`,
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}
