/**
 * Typed client for the favorites endpoints (Sprint 7).
 *
 * Mirrors `qbazaar-contracts/openapi/v1.yaml` (BE-7.x) and routes everything
 * through the shared axios instance so the toggle endpoint inherits the
 * bearer-token + 401-refresh dance. Errors are normalised into
 * `ApiClientError` so the UI can switch on stable codes.
 */
import { isAxiosError } from 'axios';
import { api } from './client';
import { ApiClientError } from './auth';
import type {
  ErrorEnvelope,
  FavoriteToggleResponse,
  FavoritedAdSummary,
  PaginatedResponse,
  SuccessEnvelope,
} from './types';

const ADS_BASE = '/api/v1/ads';
const ACCOUNT_BASE = '/api/v1/account/favorites';

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

export interface ListFavoritesParams extends Record<string, unknown> {
  page?: number;
  per_page?: number;
}

/**
 * Toggle the current user's favorite state for an ad. The backend is the
 * source of truth — we surface its `favorited` flag back so optimistic UIs
 * can reconcile after a flip-flop.
 */
export async function toggleFavorite(
  adId: string,
): Promise<FavoriteToggleResponse> {
  try {
    const { data } = await api.post<SuccessEnvelope<FavoriteToggleResponse>>(
      `${ADS_BASE}/${encodeURIComponent(adId)}/favorite`,
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function listFavorites(
  params: ListFavoritesParams = {},
): Promise<PaginatedResponse<FavoritedAdSummary>> {
  try {
    const { data } = await api.get<PaginatedResponse<FavoritedAdSummary>>(
      ACCOUNT_BASE,
      { params: cleanParams(params) },
    );
    return data;
  } catch (err) {
    throw toApiClientError(err);
  }
}
