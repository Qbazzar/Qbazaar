import { ApiClientError } from '@/lib/api/auth';
import { isPhoneNotVerifiedError } from '@/lib/auth/phone-gate';
import { t, translateMaybeKey } from '@/lib/i18n/messages';

/**
 * One readable sentence for an order-cycle API error: our own copy for the
 * codes the screens expect (error-codes.md), then the API's translated
 * message key, then its message, then a generic fallback.
 */
export function dealErrorMessage(error: unknown): string {
  if (error instanceof ApiClientError) {
    const own = t(`orders.errors.${error.code}`, '');
    if (own) return own;
    const fromKey = translateMaybeKey(error.messageKey);
    if (fromKey && fromKey !== error.messageKey) return fromKey;
    if (error.message && error.status > 0) return error.message;
  }
  return t('orders.errors.generic', 'Something went wrong, please try again.');
}

/** AUTH_003 is answered by the interceptor (phone verification), so screens stay quiet. */
export function isHandledGlobally(error: unknown): boolean {
  return isPhoneNotVerifiedError(error);
}

/** First server message per field of a VALIDATION_FAILED answer, keyed by the request field. */
export function fieldErrors(error: unknown): Record<string, string> {
  if (!(error instanceof ApiClientError) || error.code !== 'VALIDATION_FAILED' || !error.details) return {};
  const out: Record<string, string> = {};
  for (const [field, messages] of Object.entries(error.details)) {
    const first = Array.isArray(messages) ? messages[0] : messages;
    if (typeof first === 'string') out[field] = first;
  }
  return out;
}
