/**
 * Typed auth API client.
 *
 * Two flavours:
 *
 * - `register` / `login` / `logout` go through the shared `api` axios instance
 *   so they pick up the Bearer-token + 401-refresh interceptors.
 * - `refresh` goes through the LOCAL Next.js route handler (`/api/auth/refresh`)
 *   because the refresh token lives in an HTTP-only cookie and must not be
 *   touched by JavaScript. The route handler proxies to the Laravel backend.
 *
 * On a successful register/login the route handler is also called via
 * `persistRefreshToken` so the cookie is in place before the user lands on the
 * authenticated area.
 */
import axios, { isAxiosError } from 'axios';
import { api } from './client';
import type {
  AuthResponseEnvelope,
  ErrorEnvelope,
  LoginRequest,
  RegisterRequest,
  SuccessEnvelope,
  Token,
} from './types';

export type LoginPayload = LoginRequest;
export type RegisterPayload = RegisterRequest;

/**
 * Custom error so callers can map error codes to UX without parsing axios shape.
 * Validation `details` survives so React Hook Form can show per-field messages.
 */
export class ApiClientError extends Error {
  public readonly status: number;
  public readonly code: string;
  public readonly messageKey: string;
  public readonly details?: Record<string, string[]> | null;
  public readonly requestId?: string;

  constructor(params: {
    status: number;
    code: string;
    messageKey: string;
    message: string;
    details?: Record<string, string[]> | null;
    requestId?: string;
  }) {
    super(params.message);
    this.name = 'ApiClientError';
    this.status = params.status;
    this.code = params.code;
    this.messageKey = params.messageKey;
    this.details = params.details ?? null;
    this.requestId = params.requestId;
  }
}

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
 * Hand the refresh token off to our Next.js route so it can set the HTTP-only
 * cookie. Best-effort: a failure here is logged but doesn't sink the flow,
 * because the in-memory tokens still work for the current session.
 */
async function persistRefreshToken(refreshToken: string): Promise<void> {
  try {
    await axios.post(
      '/api/auth/session',
      { refresh_token: refreshToken },
      { withCredentials: true, headers: { 'Content-Type': 'application/json' } },
    );
  } catch {
    // swallowed on purpose — see jsdoc
  }
}

async function clearRefreshTokenCookie(): Promise<void> {
  try {
    await axios.delete('/api/auth/session', { withCredentials: true });
  } catch {
    // best-effort
  }
}

export async function register(
  payload: RegisterPayload,
): Promise<AuthResponseEnvelope['data']> {
  try {
    const { data } = await api.post<AuthResponseEnvelope>(
      '/api/v1/auth/register',
      payload,
    );
    await persistRefreshToken(data.data.tokens.refresh_token);
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function login(
  payload: LoginPayload,
): Promise<AuthResponseEnvelope['data']> {
  try {
    const { data } = await api.post<AuthResponseEnvelope>(
      '/api/v1/auth/login',
      payload,
    );
    await persistRefreshToken(data.data.tokens.refresh_token);
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function logout(): Promise<void> {
  try {
    await api.post('/api/v1/auth/logout');
  } catch (err) {
    // Even if the backend call fails, we still want to clear our local cookie.
    // We only swallow expected auth errors; surface anything else.
    if (
      !isAxiosError(err) ||
      ![401, 204].includes(err.response?.status ?? 0)
    ) {
      throw toApiClientError(err);
    }
  } finally {
    await clearRefreshTokenCookie();
  }
}

/**
 * Hits the LOCAL route handler — it's the only thing that knows the
 * HTTP-only refresh-token cookie.
 *
 * Returns the new access token + refreshed user info. The new refresh token
 * never leaves the server.
 */
export async function refresh(): Promise<{ token: Token; user?: never } | null> {
  try {
    const { data } = await axios.post<SuccessEnvelope<{ token: Token }>>(
      '/api/auth/refresh',
      undefined,
      { withCredentials: true },
    );
    return { token: data.data.token };
  } catch {
    return null;
  }
}
