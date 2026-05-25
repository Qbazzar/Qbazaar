/**
 * Typed client for the support tickets domain (Sprint 12).
 *
 * Public surface:
 * - `createTicket` is callable by both authenticated and anonymous users.
 *   Anonymous payloads must include `email`; the backend enforces it.
 * - The remaining endpoints sit behind auth and are scoped to the caller.
 *
 * `TICKET_INVALID_TRANSITION` is the soft-error raised when replying to a
 * `resolved` or `closed` ticket. The UI catches it and surfaces a toast.
 */
import { isAxiosError } from 'axios';
import { api } from './client';
import { ApiClientError } from './auth';
import type {
  ErrorEnvelope,
  MakeSupportTicketRequest,
  PaginatedResponse,
  SuccessEnvelope,
  SupportReply,
  SupportTicket,
  SupportTicketListItem,
} from './types';

const PUBLIC_BASE = '/api/v1/support';
const ACCOUNT_BASE = '/api/v1/account/support';

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

export interface ListMyTicketsParams extends Record<string, unknown> {
  status?: 'open' | 'in_progress' | 'waiting_user' | 'resolved' | 'closed';
  page?: number;
  per_page?: number;
}

export async function createTicket(
  payload: MakeSupportTicketRequest,
): Promise<SupportTicket> {
  try {
    const { data } = await api.post<SuccessEnvelope<SupportTicket>>(
      `${PUBLIC_BASE}/tickets`,
      payload,
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function listMyTickets(
  params: ListMyTicketsParams = {},
): Promise<PaginatedResponse<SupportTicketListItem>> {
  try {
    const { data } = await api.get<PaginatedResponse<SupportTicketListItem>>(
      `${ACCOUNT_BASE}/tickets`,
      { params: cleanParams(params) },
    );
    return data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function getTicket(id: string): Promise<SupportTicket> {
  try {
    const { data } = await api.get<SuccessEnvelope<SupportTicket>>(
      `${ACCOUNT_BASE}/tickets/${encodeURIComponent(id)}`,
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function replyToTicket(
  id: string,
  body: string,
): Promise<SupportReply> {
  try {
    const { data } = await api.post<SuccessEnvelope<SupportReply>>(
      `${ACCOUNT_BASE}/tickets/${encodeURIComponent(id)}/reply`,
      { body },
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}
