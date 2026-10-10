import { ApiClientError } from '@/lib/api/auth';
import { t } from '@/lib/i18n/messages';

/** The translation of `key`, or undefined when the dictionaries have none. */
function knownMessage(key: string): string | undefined {
  const value = t(key);
  return value === key ? undefined : value;
}

/**
 * What to tell the user about a failed account request: the first field
 * message of a validation error, else the translated error code, else the
 * API's own message.
 */
export function apiErrorMessage(err: unknown): string {
  if (!(err instanceof ApiClientError)) return t('auth.errors.unknown');
  const fieldMessage = err.details ? Object.values(err.details)[0]?.[0] : undefined;
  if (err.code === 'VALIDATION_FAILED' && fieldMessage) return fieldMessage;
  return (
    knownMessage(`account.errors.${err.code}`) ??
    knownMessage(`auth.errors.${err.code}`) ??
    fieldMessage ??
    err.message
  );
}
