/**
 * Typed client for the notifications domain (Sprint 10).
 *
 * Mirrors `qbazaar-contracts/openapi/v1.yaml` and reuses the shared axios
 * instance so cookies, locale and the 401-refresh dance are inherited. All
 * errors are normalised into `ApiClientError` so the UI can switch on stable
 * `NotificationErrorCode` values.
 */
import { isAxiosError } from 'axios';
import { api } from './client';
import { ApiClientError } from './auth';
import type {
  ErrorEnvelope,
  Notification,
  PaginatedResponse,
  SuccessEnvelope,
} from './types';

const NOTIFICATIONS_BASE = '/api/v1/account/notifications';
const DEVICE_TOKENS_BASE = '/api/v1/account/device-tokens';

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

export interface ListNotificationsParams extends Record<string, unknown> {
  /** When truthy the backend filters to unread rows only. */
  unread?: boolean | 1;
  page?: number;
  per_page?: number;
}

export interface UnreadNotificationsCountResponse {
  total: number;
}

export interface MarkAllReadResponse {
  marked: number;
}

export async function listNotifications(
  params: ListNotificationsParams = {},
): Promise<PaginatedResponse<Notification>> {
  try {
    // The backend accepts `unread=1`; coerce booleans for callers convenience.
    const normalised = { ...params } as Record<string, unknown>;
    if (normalised.unread === true) normalised.unread = 1;
    const { data } = await api.get<PaginatedResponse<Notification>>(
      NOTIFICATIONS_BASE,
      { params: cleanParams(normalised) },
    );
    return data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function markNotificationRead(id: string): Promise<Notification> {
  try {
    const { data } = await api.post<SuccessEnvelope<Notification>>(
      `${NOTIFICATIONS_BASE}/${encodeURIComponent(id)}/read`,
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function markAllNotificationsRead(): Promise<MarkAllReadResponse> {
  try {
    const { data } = await api.post<SuccessEnvelope<MarkAllReadResponse>>(
      `${NOTIFICATIONS_BASE}/read-all`,
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function deleteNotification(id: string): Promise<void> {
  try {
    await api.delete(`${NOTIFICATIONS_BASE}/${encodeURIComponent(id)}`);
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function getUnreadNotificationsCount(): Promise<UnreadNotificationsCountResponse> {
  try {
    const { data } = await api.get<
      SuccessEnvelope<UnreadNotificationsCountResponse>
    >(`${NOTIFICATIONS_BASE}/unread-count`);
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

// ── Device tokens (web push) ───────────────────────────────────────────────

export interface RegisterDeviceTokenInput {
  /** FCM registration token issued to this browser. */
  token: string;
  platform: 'web';
}

/** The raw FCM token is intentionally never echoed back by the API. */
export interface DeviceToken {
  id: string;
  platform: 'web' | 'android' | 'ios';
  created_at: string;
}

export async function registerDeviceToken(
  input: RegisterDeviceTokenInput,
): Promise<DeviceToken> {
  try {
    const { data } = await api.post<SuccessEnvelope<DeviceToken>>(
      DEVICE_TOKENS_BASE,
      input,
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function unregisterDeviceToken(token: string): Promise<void> {
  try {
    // FCM tokens are too long (and too sensitive) for the URL, so the
    // contract carries the token in the DELETE body. Always 204 server-side.
    await api.delete(DEVICE_TOKENS_BASE, { data: { token } });
  } catch (err) {
    throw toApiClientError(err);
  }
}
