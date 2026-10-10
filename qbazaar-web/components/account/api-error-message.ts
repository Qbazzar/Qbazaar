import { ApiClientError } from '@/lib/api/auth';
import { t } from '@/lib/i18n/messages';

/** The translation of `key`, or undefined when the dictionaries have none. */
function knownMessage(key: string, vars?: Record<string, string>): string | undefined {
  const value = vars ? t(key, vars) : t(key);
  return value === key ? undefined : value;
}

/**
 * A scalar from the error's `details`. The API puts plain strings there for
 * the account-deletion refusals (`amount`, `currency`, `order_id`), although
 * the client type is the validation shape (string[]).
 */
function detailValue(err: ApiClientError, key: string): string | undefined {
  const value = (err.details as Record<string, unknown> | null | undefined)?.[key];
  const first = Array.isArray(value) ? value[0] : value;
  return typeof first === 'string' || typeof first === 'number' ? String(first) : undefined;
}

function messageVars(err: ApiClientError): Record<string, string> {
  return {
    amount: detailValue(err, 'amount') ?? '',
    currency: detailValue(err, 'currency') ?? '',
  };
}

export interface ErrorLink {
  href: string;
  label: string;
}

/** Where the user can fix an account-deletion refusal, when there is such a page. */
export function apiErrorLink(err: unknown): ErrorLink | null {
  if (!(err instanceof ApiClientError)) return null;
  switch (err.code) {
    case 'ACCOUNT_003':
    case 'ACCOUNT_005':
    case 'ACCOUNT_006':
      return { href: '/account/wallet', label: t('account.errors.link_wallet') };
    case 'ACCOUNT_004': {
      const orderId = detailValue(err, 'order_id');
      return {
        href: orderId ? `/account/orders/${encodeURIComponent(orderId)}` : '/account/orders',
        label: t('account.errors.link_order'),
      };
    }
    default:
      return null;
  }
}

/**
 * What to tell the user about a failed account request: the first field
 * message of a validation error, else the translated error code (with the
 * amount and currency the API sent), else the API's own message.
 */
export function apiErrorMessage(err: unknown): string {
  if (!(err instanceof ApiClientError)) return t('auth.errors.unknown');
  const fieldMessage = err.details ? Object.values(err.details)[0]?.[0] : undefined;
  if (err.code === 'VALIDATION_FAILED' && typeof fieldMessage === 'string') return fieldMessage;
  const vars = messageVars(err);
  return (
    knownMessage(`account.errors.${err.code}`, vars) ??
    knownMessage(`auth.errors.${err.code}`, vars) ??
    (typeof fieldMessage === 'string' ? fieldMessage : undefined) ??
    err.message
  );
}
