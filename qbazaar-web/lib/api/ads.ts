/**
 * Typed client for the ads endpoints (Sprint 4 + 5).
 *
 * Mirrors `qbazaar-contracts/openapi/v1.yaml` and routes everything through
 * the shared `api` axios instance so list calls inherit the bearer-token +
 * 401-refresh dance just like the auth client. Each helper unwraps the
 * `{ success, data }` envelope so callers stay envelope-agnostic.
 */
import { isAxiosError } from 'axios';
import { api } from './client';
import { ApiClientError } from './auth';
import type {
  Ad,
  AdSummary,
  CreateAdRequest,
  ErrorEnvelope,
  PaginatedResponse,
  SuccessEnvelope,
  UpdateAdRequest,
} from './types';

const BASE = '/api/v1/ads';

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

export interface ListAdsParams extends Record<string, unknown> {
  category_id?: string;
  location_id?: string;
  page?: number;
  per_page?: number;
  status?: string;
}

/**
 * Drop undefined/null entries so we don't ship `?status=undefined` query
 * strings to the backend (Laravel's request validation chokes on those).
 */
function cleanParams<T extends Record<string, unknown>>(params: T): Partial<T> {
  const out: Partial<T> = {};
  for (const [k, v] of Object.entries(params)) {
    if (v !== undefined && v !== null && v !== '') {
      (out as Record<string, unknown>)[k] = v;
    }
  }
  return out;
}

// ── Public list / detail ───────────────────────────────────────────────────

export async function listAds(
  params: ListAdsParams = {},
): Promise<PaginatedResponse<AdSummary>> {
  try {
    const { data } = await api.get<PaginatedResponse<AdSummary>>(BASE, {
      params: cleanParams(params),
    });
    return data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function getAd(id: string): Promise<Ad> {
  try {
    const { data } = await api.get<SuccessEnvelope<Ad>>(
      `${BASE}/${encodeURIComponent(id)}`,
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

// ── Owner CRUD ─────────────────────────────────────────────────────────────

export async function createAd(payload: CreateAdRequest): Promise<Ad> {
  try {
    const { data } = await api.post<SuccessEnvelope<Ad>>(BASE, payload);
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function updateAd(
  id: string,
  payload: UpdateAdRequest,
): Promise<Ad> {
  try {
    const { data } = await api.put<SuccessEnvelope<Ad>>(
      `${BASE}/${encodeURIComponent(id)}`,
      payload,
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function deleteAd(id: string): Promise<void> {
  try {
    await api.delete(`${BASE}/${encodeURIComponent(id)}`);
  } catch (err) {
    throw toApiClientError(err);
  }
}

// ── Discovery: similar + featured ──────────────────────────────────────────

/**
 * "More like this" rail rendered on the ad-detail page. Backed by
 * `GET /api/v1/ads/{id}/similar`. Returns an unwrapped list — the endpoint
 * does not paginate.
 */
export async function getSimilarAds(id: string): Promise<AdSummary[]> {
  try {
    const { data } = await api.get<SuccessEnvelope<AdSummary[]>>(
      `${BASE}/${encodeURIComponent(id)}/similar`,
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

/**
 * Editorially-promoted ads shown on the homepage. Backed by
 * `GET /api/v1/ads/featured`. Returns an unwrapped list.
 */
export async function getFeaturedAds(): Promise<AdSummary[]> {
  try {
    const { data } = await api.get<SuccessEnvelope<AdSummary[]>>(
      `${BASE}/featured`,
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

// ── State transitions ──────────────────────────────────────────────────────

/**
 * Publish a draft ad.
 *
 * Accepts an optional `idempotencyKey` so the caller can guarantee at-most-
 * once semantics across retries (network blip → user re-clicks → axios
 * retries on transient 5xx). The backend keys its publish cache off
 * `X-Idempotency-Key` so the same key returns the cached response without
 * double-charging quotas or re-firing notifications. See BE-5.33.
 */
export async function publishAd(
  id: string,
  options: { idempotencyKey?: string } = {},
): Promise<Ad> {
  try {
    const headers: Record<string, string> = {};
    if (options.idempotencyKey) {
      headers['X-Idempotency-Key'] = options.idempotencyKey;
    }
    const { data } = await api.post<SuccessEnvelope<Ad>>(
      `${BASE}/${encodeURIComponent(id)}/publish`,
      undefined,
      Object.keys(headers).length ? { headers } : undefined,
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function markSold(id: string): Promise<Ad> {
  try {
    const { data } = await api.post<SuccessEnvelope<Ad>>(
      `${BASE}/${encodeURIComponent(id)}/mark-sold`,
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function renewAd(id: string): Promise<Ad> {
  try {
    const { data } = await api.post<SuccessEnvelope<Ad>>(
      `${BASE}/${encodeURIComponent(id)}/renew`,
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

// ── Account scope ──────────────────────────────────────────────────────────

export interface MyAdsParams extends Record<string, unknown> {
  page?: number;
  per_page?: number;
  /** Optional status filter — mapped to a tab in the My Ads UI. */
  status?: string;
}

export async function getMyAds(
  params: MyAdsParams = {},
): Promise<PaginatedResponse<AdSummary>> {
  try {
    const { data } = await api.get<PaginatedResponse<AdSummary>>(
      '/api/v1/account/ads',
      { params: cleanParams(params) },
    );
    return data;
  } catch (err) {
    throw toApiClientError(err);
  }
}
