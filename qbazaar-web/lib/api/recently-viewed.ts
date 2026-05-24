/**
 * Typed client for the recently-viewed endpoints (Sprint 7).
 *
 * Tracking fires on every ad-detail mount; the backend dedupes per
 * (user_id|session_id, ad_id) within a short window. We forward
 * `X-Session-Id` so anonymous visitors get a stable history that the
 * backend can stitch onto the user record after sign-in.
 */
import { isAxiosError } from 'axios';
import { api } from './client';
import { ApiClientError } from './auth';
import { getOrCreateSessionId } from '@/lib/session/anonSessionId';
import type {
  ErrorEnvelope,
  PaginatedResponse,
  RecentlyViewedAdSummary,
} from './types';

const ADS_BASE = '/api/v1/ads';
const ACCOUNT_BASE = '/api/v1/account/recently-viewed';

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

export interface ListRecentlyViewedParams extends Record<string, unknown> {
  page?: number;
  per_page?: number;
}

/**
 * Silent fire-and-forget recorder. The backend returns 204 — there's nothing
 * for the UI to render. We only attach the session header when one is
 * available client-side (SSR returns "" so the header is omitted).
 */
export async function trackView(adId: string): Promise<void> {
  const sessionId = getOrCreateSessionId();
  const headers: Record<string, string> = {};
  if (sessionId) headers['X-Session-Id'] = sessionId;
  try {
    await api.post(`${ADS_BASE}/${encodeURIComponent(adId)}/view`, undefined, {
      headers,
    });
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function listRecentlyViewed(
  params: ListRecentlyViewedParams = {},
): Promise<PaginatedResponse<RecentlyViewedAdSummary>> {
  try {
    const { data } = await api.get<PaginatedResponse<RecentlyViewedAdSummary>>(
      ACCOUNT_BASE,
      { params: cleanParams(params) },
    );
    return data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function clearRecentlyViewed(): Promise<void> {
  try {
    await api.delete(ACCOUNT_BASE);
  } catch (err) {
    throw toApiClientError(err);
  }
}
