import type { AxiosRequestConfig } from 'axios';

export const TURNSTILE_HEADER = 'X-Turnstile-Token';

/**
 * Request config carrying the Turnstile token for the endpoints guarded by
 * the API's `turnstile` middleware. Without a token nothing is added, so the
 * request is unchanged while the widget is disabled.
 */
export function withTurnstile(token?: string): AxiosRequestConfig {
  return token ? { headers: { [TURNSTILE_HEADER]: token } } : {};
}
