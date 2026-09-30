const INTERNAL_ORIGIN = 'http://internal.invalid';

// Rejects protocol-relative ("//host") and backslash forms ("/\host"), which
// browsers normalise into an external origin.
const LOOKS_EXTERNAL = /^\/[\\/]|\\/;

// eslint-disable-next-line no-control-regex
const CONTROL_OR_SPACE = /[\u0000-\u001F\u007F\s]/;

/**
 * Returns `raw` as a same-origin path (path + query + hash) when it is safe
 * to navigate to, otherwise `fallback`. Used for every `from` / `continue`
 * query parameter so none of them can become an open redirect.
 */
export function safeReturnTo(
  raw: string | null | undefined,
  fallback = '/',
): string {
  if (!raw || !raw.startsWith('/')) return fallback;
  if (LOOKS_EXTERNAL.test(raw) || CONTROL_OR_SPACE.test(raw)) return fallback;

  try {
    const url = new URL(raw, INTERNAL_ORIGIN);
    if (url.origin !== INTERNAL_ORIGIN) return fallback;
    return `${url.pathname}${url.search}${url.hash}`;
  } catch {
    return fallback;
  }
}

/** Current browser location as a return target; `/` on the server. */
export function currentLocationPath(): string {
  if (typeof window === 'undefined') return '/';
  return `${window.location.pathname}${window.location.search}`;
}
