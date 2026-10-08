import { isAxiosError, type AxiosResponse } from 'axios';

import { ApiClientError } from './auth';
import type { ErrorEnvelope, SuccessEnvelope } from './types';

/**
 * Shared plumbing for the order-cycle API modules: one error normaliser and
 * one unwrapper instead of a copy per module.
 */
export function toApiClientError(err: unknown): ApiClientError {
  if (err instanceof ApiClientError) return err;
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
    return new ApiClientError({ status: 0, code: 'NETWORK_ERROR', messageKey: 'errors.network', message: err.message });
  }
  return new ApiClientError({ status: 0, code: 'UNKNOWN_ERROR', messageKey: 'errors.unknown', message: 'Unknown error' });
}

/** Awaits an envelope request and returns its `data`, throwing `ApiClientError` on failure. */
export async function unwrap<T>(request: Promise<AxiosResponse<SuccessEnvelope<T>>>): Promise<T> {
  try {
    const { data } = await request;
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

/** Awaits a request whose whole body is wanted (cursor pages carry `meta`). */
export async function unwrapBody<T>(request: Promise<AxiosResponse<T>>): Promise<T> {
  try {
    const { data } = await request;
    return data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

/** `X-Idempotency-Key` header: the same key on a retry replays the first success. */
export function idempotent(key: string | undefined): { headers?: Record<string, string> } {
  return key ? { headers: { 'X-Idempotency-Key': key } } : {};
}

/** Builds query params, dropping empty values. */
export function query(params: Record<string, string | number | null | undefined>): Record<string, string | number> {
  const out: Record<string, string | number> = {};
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null && value !== '') out[key] = value;
  }
  return out;
}

/** A detail the API attached to an error, e.g. `details.withdrawable` on WALLET_003. */
export function errorDetail(error: unknown, key: string): string | null {
  if (!(error instanceof ApiClientError) || !error.details) return null;
  const value: unknown = (error.details as Record<string, unknown>)[key];
  if (typeof value === 'string' || typeof value === 'number') return String(value);
  if (Array.isArray(value) && value.length > 0) return String(value[0]);
  return null;
}
