/**
 * Typed client for the search + saved-searches endpoints (Sprint 6).
 *
 * Mirrors `qbazaar-contracts/openapi/v1.yaml` (BE-6.x) and routes everything
 * through the shared axios instance so the search-bar suggestions endpoint
 * benefits from the same 401-refresh dance when the user is signed in (the
 * backend personalises suggestions when authenticated).
 */
import { isAxiosError } from 'axios';
import { api } from './client';
import { ApiClientError } from './auth';
import type {
  ErrorEnvelope,
  SavedSearch,
  SearchQueryParams,
  SearchResponse,
  SearchSuggestion,
  SuccessEnvelope,
} from './types';

const SEARCH_BASE = '/api/v1/search';
const SAVED_BASE = '/api/v1/account/saved-searches';

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

/**
 * Drop undefined/null/empty entries so the URL stays clean. Laravel's request
 * validation rejects `?price_min=` outright, so we have to strip those
 * client-side.
 */
function cleanParams<T extends Record<string, unknown>>(params: T): Partial<T> {
  const out: Partial<T> = {};
  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === '') continue;
    (out as Record<string, unknown>)[key] = value;
  }
  return out;
}

// ── Public search ──────────────────────────────────────────────────────────

export async function runSearch(
  params: SearchQueryParams = {},
): Promise<SearchResponse> {
  try {
    const { data } = await api.get<SearchResponse>(SEARCH_BASE, {
      params: flattenSearchParams(cleanParams(params)),
    });
    return data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

/**
 * Flatten the nested `custom_fields` object into bracket-notation query keys
 * (`custom_fields[make]=Toyota`, `custom_fields[year][min]=2015`) that Laravel
 * parses back into a nested array — instead of relying on axios's object
 * serialisation.
 */
function flattenSearchParams(
  params: Partial<SearchQueryParams>,
): Record<string, unknown> {
  const { custom_fields, ...rest } = params;
  const out: Record<string, unknown> = { ...rest };

  if (custom_fields && typeof custom_fields === 'object') {
    for (const [key, value] of Object.entries(custom_fields)) {
      if (value === undefined || value === null || value === '') continue;
      if (typeof value === 'object') {
        if (value.min !== undefined && value.min !== null) {
          out[`custom_fields[${key}][min]`] = value.min;
        }
        if (value.max !== undefined && value.max !== null) {
          out[`custom_fields[${key}][max]`] = value.max;
        }
      } else {
        out[`custom_fields[${key}]`] = value;
      }
    }
  }

  return out;
}

export async function getSuggestions(q: string): Promise<SearchSuggestion[]> {
  try {
    const { data } = await api.get<SuccessEnvelope<SearchSuggestion[]>>(
      `${SEARCH_BASE}/suggestions`,
      { params: { q } },
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

// ── Saved searches (auth) ──────────────────────────────────────────────────

export async function listSavedSearches(): Promise<SavedSearch[]> {
  try {
    const { data } = await api.get<SuccessEnvelope<SavedSearch[]>>(SAVED_BASE);
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

export interface CreateSavedSearchPayload {
  name: string;
  query_params: SearchQueryParams;
}

export async function createSavedSearch(
  payload: CreateSavedSearchPayload,
): Promise<SavedSearch> {
  try {
    const { data } = await api.post<SuccessEnvelope<SavedSearch>>(
      SAVED_BASE,
      payload,
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function deleteSavedSearch(id: string): Promise<void> {
  try {
    await api.delete(`${SAVED_BASE}/${encodeURIComponent(id)}`);
  } catch (err) {
    throw toApiClientError(err);
  }
}
