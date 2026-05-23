/**
 * Shared TypeScript types mirroring qbazaar-contracts/openapi/v1.yaml.
 *
 * These shapes are 1:1 with the OpenAPI schemas and should change ONLY when
 * the contract changes. The Prism mock and the eventual Laravel backend both
 * promise these envelopes.
 */

// ── Auth domain ─────────────────────────────────────────────────────────────
export type AccountType = 'private' | 'business';

export type UserStatus =
  | 'active'
  | 'suspended'
  | 'deactivated'
  | 'pending_deletion';

export type Language = 'ar' | 'en';

export interface User {
  id: string;
  full_name: string;
  email: string;
  phone: string;
  account_type: AccountType;
  status: UserStatus;
  email_verified: boolean;
  phone_verified: boolean;
  language: Language;
  avatar_url: string | null;
  created_at: string;
}

export interface Token {
  access_token: string;
  refresh_token: string;
  token_type: 'Bearer';
  expires_in: number;
}

// ── Request bodies ──────────────────────────────────────────────────────────
export interface RegisterRequest {
  full_name: string;
  email: string;
  phone: string;
  password: string;
  account_type: AccountType;
  language?: Language;
  accepted_terms: true;
}

export interface LoginRequest {
  identifier: string;
  password: string;
}

export interface RefreshRequest {
  refresh_token: string;
}

// ── Envelopes ──────────────────────────────────────────────────────────────
export interface SuccessEnvelope<T> {
  success: true;
  data: T;
}

export interface ApiError {
  code: string;
  message_key: string;
  message: string;
  details?: Record<string, string[]> | null;
  request_id?: string;
}

export interface ErrorEnvelope {
  success: false;
  error: ApiError;
}

export type AuthResponseData = {
  user: User;
  tokens: Token;
};

export type AuthResponseEnvelope = SuccessEnvelope<AuthResponseData>;

// Stable list of error codes the UI switches on (see error-codes.md).
export const AuthErrorCode = {
  InvalidCredentials: 'AUTH_001',
  AccountSuspended: 'AUTH_002',
  PhoneNotVerified: 'AUTH_003',
  OtpExpired: 'AUTH_004',
  OtpInvalid: 'AUTH_005',
  AuthRateLimited: 'AUTH_006',
  EmailExists: 'AUTH_007',
  PhoneExists: 'AUTH_008',
  TokenExpired: 'AUTH_009',
  TokenInvalid: 'AUTH_010',
  ValidationFailed: 'VALIDATION_FAILED',
  RateLimited: 'RATE_LIMIT_EXCEEDED',
} as const;

export type AuthErrorCodeValue =
  (typeof AuthErrorCode)[keyof typeof AuthErrorCode];
