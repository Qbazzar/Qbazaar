import { isAxiosError } from 'axios';
import { AuthErrorCode, type User } from '@/lib/api/types';
import { qatarPhoneRegex } from '@/lib/validation/auth';
import { safeReturnTo } from '@/lib/navigation/safe-return-to';

/**
 * Publishing an ad, starting a chat, sending a message and making an offer
 * all sit behind the backend `phone.verified` middleware (403 AUTH_003).
 * These helpers decide where a user must go before such an action.
 */
export type PhoneGateStatus = 'loading' | 'guest' | 'unverified' | 'verified';

export const PHONE_VERIFICATION_PATH = '/account/verification';
export const VERIFY_OTP_PATH = '/verify-otp';
export const LOGIN_PATH = '/login';

interface PhoneGateInput {
  isHydrated: boolean;
  isAuthenticated: boolean;
  user: Pick<User, 'phone_verified'> | null;
}

export function resolvePhoneGateStatus({
  isHydrated,
  isAuthenticated,
  user,
}: PhoneGateInput): PhoneGateStatus {
  if (!isHydrated) return 'loading';
  if (!isAuthenticated || !user) return 'guest';
  return user.phone_verified ? 'verified' : 'unverified';
}

export function loginHref(returnTo: string): string {
  return `${LOGIN_PATH}?from=${encodeURIComponent(safeReturnTo(returnTo))}`;
}

export function phoneVerificationHref(returnTo: string): string {
  return `${PHONE_VERIFICATION_PATH}?continue=${encodeURIComponent(safeReturnTo(returnTo))}`;
}

export function verifyOtpHref(phone: string, continueTo: string): string {
  const params = new URLSearchParams({
    phone,
    continue: safeReturnTo(continueTo, PHONE_VERIFICATION_PATH),
  });
  return `${VERIFY_OTP_PATH}?${params.toString()}`;
}

/** Where the user must go first, or `null` when the action may proceed. */
export function phoneGateRedirect(
  status: PhoneGateStatus,
  returnTo: string,
): string | null {
  if (status === 'guest') return loginHref(returnTo);
  if (status === 'unverified') return phoneVerificationHref(returnTo);
  return null;
}

export function hasVerifiablePhone(phone: string | null | undefined): phone is string {
  return typeof phone === 'string' && qatarPhoneRegex.test(phone);
}

/** Pages that are part of the verification flow itself. */
export function isPhoneVerificationPath(pathname: string): boolean {
  return (
    pathname.startsWith(PHONE_VERIFICATION_PATH) ||
    pathname.startsWith(VERIFY_OTP_PATH)
  );
}

/** Works for both raw axios errors and the typed `ApiClientError`. */
export function isPhoneNotVerifiedError(err: unknown): boolean {
  if (isAxiosError<{ error?: { code?: string } }>(err)) {
    return err.response?.data?.error?.code === AuthErrorCode.PhoneNotVerified;
  }
  return (
    typeof err === 'object' &&
    err !== null &&
    'code' in err &&
    (err as { code: unknown }).code === AuthErrorCode.PhoneNotVerified
  );
}
