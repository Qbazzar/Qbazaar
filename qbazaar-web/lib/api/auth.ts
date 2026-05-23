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
  ForgotPasswordRequest,
  LoginRequest,
  OtpSendRequest,
  OtpSendResponseData,
  OtpSendResponseEnvelope,
  OtpVerifyRequest,
  OtpVerifyResponseData,
  RegisterRequest,
  ResetPasswordRequest,
  SuccessEnvelope,
  Token,
  VerifyEmailQuery,
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

// ── OTP ─────────────────────────────────────────────────────────────────────

/**
 * Request a fresh 6-digit OTP for the given Qatar phone.
 * The backend returns the cooldown window so the UI can disable resend.
 */
export async function sendOtp(
  payload: OtpSendRequest,
): Promise<OtpSendResponseData> {
  try {
    const { data } = await api.post<OtpSendResponseEnvelope>(
      '/api/v1/auth/send-otp',
      payload,
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

/**
 * Verify a 6-digit OTP. On success the phone is marked verified server-side.
 * Throws `ApiClientError` with `AUTH_004` (expired) or `AUTH_005` (invalid) on
 * the unhappy paths.
 */
export async function verifyOtp(
  payload: OtpVerifyRequest,
): Promise<OtpVerifyResponseData> {
  try {
    const { data } = await api.post<SuccessEnvelope<OtpVerifyResponseData>>(
      '/api/v1/auth/verify-otp',
      payload,
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

/**
 * Same shape as `sendOtp` but hits the dedicated resend endpoint so the
 * backend can apply a stricter rate limit.
 */
export async function resendOtp(
  payload: OtpSendRequest,
): Promise<OtpSendResponseData> {
  try {
    const { data } = await api.post<OtpSendResponseEnvelope>(
      '/api/v1/auth/resend-otp',
      payload,
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

// ── Password reset ──────────────────────────────────────────────────────────

/**
 * Trigger the password-reset email. The contract guarantees a generic 202
 * regardless of whether the email exists (anti-enumeration).
 */
export async function forgotPassword(
  payload: ForgotPasswordRequest,
): Promise<void> {
  try {
    await api.post('/api/v1/auth/forgot-password', payload);
  } catch (err) {
    throw toApiClientError(err);
  }
}

/**
 * Consume the reset token (delivered in the email link) and set a new
 * password. Validation errors arrive as 422 with per-field details.
 */
export async function resetPassword(
  payload: ResetPasswordRequest,
): Promise<void> {
  try {
    await api.post('/api/v1/auth/reset-password', payload);
  } catch (err) {
    throw toApiClientError(err);
  }
}

// ── Email verification ──────────────────────────────────────────────────────

/**
 * Re-send the email-verification link to the currently authenticated user.
 * Requires the Bearer header, which the axios interceptor adds automatically.
 */
export async function sendEmailVerification(): Promise<void> {
  try {
    await api.post('/api/v1/auth/send-email-verification');
  } catch (err) {
    throw toApiClientError(err);
  }
}

/**
 * Confirm an email address via the signed link from the verification mail.
 *
 * The Laravel "signed URL" pattern carries the cryptographic `signature` and
 * `expires` outside the path, so we forward them as query params and let the
 * backend re-validate the signature.
 */
export async function verifyEmail(
  id: string,
  hash: string,
  query: VerifyEmailQuery = {},
): Promise<void> {
  try {
    await api.get(`/api/v1/auth/verify-email/${id}/${hash}`, {
      params: query,
    });
  } catch (err) {
    throw toApiClientError(err);
  }
}
