/**
 * Typed account API client.
 *
 * Every call goes through the shared `api` axios instance so the Bearer token
 * + 401-refresh interceptors apply automatically. Error responses are mapped
 * to `ApiClientError` (defined in `./auth.ts`) so React Hook Form can attach
 * per-field messages.
 *
 * Contract source of truth: `qbazaar-contracts/openapi/v1.yaml`
 * (BE-2.1 → BE-2.10 — the backend agent ships these in this same wave).
 */
import { api } from './client';
import { ApiClientError } from './auth';
import { isAxiosError } from 'axios';
import type { SavedAddress } from './commerce-types';
import type {
  AccountProfile,
  AccountSummary,
  BlockedUser,
  BusinessProfile,
  ChangePasswordRequest,
  ContactChangeRequest,
  DataExportResponse,
  DeactivateAccountRequest,
  DeleteAccountRequest,
  EmailPreferences,
  ErrorEnvelope,
  OtpSendResponseData,
  PrivacySettings,
  ReauthCodeResponse,
  SavedAddressInput,
  SuccessEnvelope,
  UpdateProfileRequest,
  UserSession,
  VerificationStatus,
} from './types';

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

// ── Dashboard summary ──────────────────────────────────────────────────────
export async function getAccountSummary(): Promise<AccountSummary> {
  try {
    const { data } = await api.get<SuccessEnvelope<AccountSummary>>(
      '/api/v1/account/summary',
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

// ── Profile ────────────────────────────────────────────────────────────────
export async function getAccountProfile(): Promise<AccountProfile> {
  try {
    const { data } = await api.get<SuccessEnvelope<AccountProfile>>(
      '/api/v1/account/profile',
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function updateAccountProfile(
  payload: UpdateProfileRequest,
): Promise<AccountProfile> {
  try {
    const { data } = await api.put<SuccessEnvelope<AccountProfile>>(
      '/api/v1/account/profile',
      payload,
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

// ── Password ───────────────────────────────────────────────────────────────
export async function changePassword(
  payload: ChangePasswordRequest,
): Promise<void> {
  try {
    // The endpoint expects `password` (+ `password_confirmation`), per Laravel's
    // `confirmed` rule — the form models it as `new_password`, so map it here.
    // Sending `new_password` left `password` empty and the change always 422-ed.
    await api.put('/api/v1/account/password', {
      current_password: payload.current_password,
      password: payload.new_password,
      password_confirmation: payload.password_confirmation,
    });
  } catch (err) {
    throw toApiClientError(err);
  }
}

// ── Sessions ───────────────────────────────────────────────────────────────
export async function listSessions(): Promise<UserSession[]> {
  try {
    const { data } = await api.get<SuccessEnvelope<UserSession[]>>(
      '/api/v1/account/sessions',
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function revokeSession(id: string): Promise<void> {
  try {
    await api.delete(`/api/v1/account/sessions/${encodeURIComponent(id)}`);
  } catch (err) {
    throw toApiClientError(err);
  }
}

// ── Privacy settings ───────────────────────────────────────────────────────
export async function getPrivacySettings(): Promise<PrivacySettings> {
  try {
    const { data } = await api.get<SuccessEnvelope<PrivacySettings>>(
      '/api/v1/account/privacy-settings',
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function updatePrivacySettings(
  payload: PrivacySettings,
): Promise<PrivacySettings> {
  try {
    const { data } = await api.put<SuccessEnvelope<PrivacySettings>>(
      '/api/v1/account/privacy-settings',
      payload,
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

// ── Blocked users ──────────────────────────────────────────────────────────
export async function listBlockedUsers(): Promise<BlockedUser[]> {
  try {
    const { data } = await api.get<SuccessEnvelope<BlockedUser[]>>(
      '/api/v1/account/blocked-users',
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

// ── Verification ───────────────────────────────────────────────────────────
export async function getVerificationStatus(): Promise<VerificationStatus> {
  try {
    const { data } = await api.get<SuccessEnvelope<VerificationStatus>>(
      '/api/v1/account/verification-status',
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

// ── Data export / deactivate / delete (Wave 2 lifecycle) ───────────────────

/**
 * Queue a "give me all my data" job server-side. The link arrives by email
 * — the response only confirms the request was accepted.
 */
export async function requestDataExport(): Promise<DataExportResponse> {
  try {
    const { data } = await api.post<SuccessEnvelope<DataExportResponse>>(
      '/api/v1/account/data-export-request',
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

/**
 * Deactivate the current account. The user must re-enter their password.
 * After success the caller is expected to clear the local auth state and
 * redirect to `/login?deactivated=1`.
 */
export async function deactivateAccount(
  payload: DeactivateAccountRequest,
): Promise<void> {
  try {
    await api.post('/api/v1/account/deactivate', payload);
  } catch (err) {
    throw toApiClientError(err);
  }
}

/**
 * Schedule the account for deletion (typically with a 30-day grace window
 * managed server-side). Caller signs out + redirects to `/login?deleted=1`.
 */
export async function requestAccountDeletion(
  payload: DeleteAccountRequest,
): Promise<void> {
  try {
    await api.delete('/api/v1/account/delete-request', { data: payload });
  } catch (err) {
    throw toApiClientError(err);
  }
}

// ── Contact changes (step-up code, then the new email or phone) ────────────

/** Emails a 6-digit step-up code to the current address. */
export async function requestReauthCode(): Promise<ReauthCodeResponse> {
  try {
    const { data } = await api.post<SuccessEnvelope<ReauthCodeResponse>>('/api/v1/account/reauth-code');
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

/** Sends a confirmation link to the new address; nothing changes until it is opened. */
export async function requestEmailChange(payload: ContactChangeRequest<'email'>): Promise<void> {
  try {
    await api.post('/api/v1/account/email', payload);
  } catch (err) {
    throw toApiClientError(err);
  }
}

/** Texts an OTP to the new number; `confirmPhoneChange` finishes the move. */
export async function requestPhoneChange(payload: ContactChangeRequest<'phone'>): Promise<OtpSendResponseData> {
  try {
    const { data } = await api.post<SuccessEnvelope<OtpSendResponseData>>('/api/v1/account/phone', payload);
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function confirmPhoneChange(code: string): Promise<AccountProfile> {
  try {
    const { data } = await api.post<SuccessEnvelope<AccountProfile>>('/api/v1/account/phone/verify', { code });
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

// ── Delivery addresses ──────────────────────────────────────────────────────

export async function listAddresses(): Promise<SavedAddress[]> {
  try {
    const { data } = await api.get<SuccessEnvelope<SavedAddress[]>>('/api/v1/account/addresses');
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function createAddress(payload: SavedAddressInput): Promise<SavedAddress> {
  try {
    const { data } = await api.post<SuccessEnvelope<SavedAddress>>('/api/v1/account/addresses', payload);
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function updateAddress(id: string, payload: Partial<SavedAddressInput>): Promise<SavedAddress> {
  try {
    const { data } = await api.patch<SuccessEnvelope<SavedAddress>>(
      `/api/v1/account/addresses/${encodeURIComponent(id)}`,
      payload,
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function deleteAddress(id: string): Promise<void> {
  try {
    await api.delete(`/api/v1/account/addresses/${encodeURIComponent(id)}`);
  } catch (err) {
    throw toApiClientError(err);
  }
}

// ── Email preferences ───────────────────────────────────────────────────────

export async function getEmailPreferences(): Promise<EmailPreferences> {
  try {
    const { data } = await api.get<SuccessEnvelope<EmailPreferences>>('/api/v1/account/email-preferences');
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

/** Topics left out keep their value. */
export async function updateEmailPreferences(payload: Partial<EmailPreferences>): Promise<EmailPreferences> {
  try {
    const { data } = await api.patch<SuccessEnvelope<EmailPreferences>>('/api/v1/account/email-preferences', payload);
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

// ── Business profile (business accounts only) ──────────────────────────────

export async function getBusinessProfile(): Promise<BusinessProfile> {
  try {
    const { data } = await api.get<SuccessEnvelope<BusinessProfile>>('/api/v1/account/business-profile');
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

/** Only the fields sent change; `null` clears one. */
export async function updateBusinessProfile(payload: Partial<BusinessProfile>): Promise<BusinessProfile> {
  try {
    const { data } = await api.put<SuccessEnvelope<BusinessProfile>>('/api/v1/account/business-profile', payload);
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}
