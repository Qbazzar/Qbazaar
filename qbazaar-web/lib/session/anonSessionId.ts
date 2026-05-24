/**
 * Anonymous session id — a UUID minted on first visit and persisted in
 * `localStorage` so the same browser keeps the same identifier across reloads.
 *
 * Used by the recently-viewed tracking endpoint, which accepts an
 * `X-Session-Id` header when the user is unauthenticated. The backend uses
 * the same id to stitch anonymous history onto the user record after sign-in.
 *
 * SSR-safe: returns an empty string when `window` is undefined so callers can
 * skip the network call without crashing during pre-render.
 */

const STORAGE_KEY = 'qb_session_id';

function generateUuid(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }
  // Minimal RFC4122 v4 fallback for environments without crypto.randomUUID.
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (ch) => {
    const r = Math.floor(Math.random() * 16);
    const v = ch === 'x' ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

/**
 * Reads (or lazily creates) the anonymous session id. Always returns an empty
 * string on the server so the caller can decide whether to omit the header.
 */
export function getOrCreateSessionId(): string {
  if (typeof window === 'undefined') return '';
  try {
    const existing = window.localStorage.getItem(STORAGE_KEY);
    if (existing && existing.length > 0) return existing;
    const next = generateUuid();
    window.localStorage.setItem(STORAGE_KEY, next);
    return next;
  } catch {
    // localStorage can throw in privacy / quota-exceeded scenarios — still
    // return a usable id for the current session, just don't persist.
    return generateUuid();
  }
}

export function clearAnonSessionId(): void {
  if (typeof window === 'undefined') return;
  try {
    window.localStorage.removeItem(STORAGE_KEY);
  } catch {
    // best-effort
  }
}
