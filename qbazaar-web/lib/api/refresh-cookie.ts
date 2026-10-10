/**
 * Shared helpers for the HTTP-only refresh-token cookie used by the
 * Next.js Route Handlers under `app/api/auth/*`.
 *
 * Centralising the cookie name and serialise options avoids subtle drift
 * between the routes that read it (`refresh`) and the routes that write it
 * (`session`).
 */
import type { NextRequest } from 'next/server';

export const REFRESH_COOKIE_NAME = 'qb_refresh_token';

/**
 * Set beside the refresh cookie when "Remember me" was left unticked, so the
 * rotation in `/api/auth/refresh` keeps the new token a browser-session
 * cookie too. Both cookies end when the browser closes.
 */
export const SESSION_ONLY_COOKIE_NAME = 'qb_session_only';

// 30 days, matching the contract's refresh-token lifetime.
const REFRESH_COOKIE_MAX_AGE = 60 * 60 * 24 * 30;

/** `maxAge` in seconds; `'session'` leaves it out, so the cookie lasts until the browser closes. */
export function refreshCookieOptions(maxAge: number | 'session' = REFRESH_COOKIE_MAX_AGE) {
  return {
    httpOnly: true,
    secure: process.env.NODE_ENV === 'production',
    sameSite: 'lax' as const,
    path: '/',
    ...(maxAge === 'session' ? {} : { maxAge }),
  };
}

export function readRefreshCookie(req: NextRequest): string | undefined {
  return req.cookies.get(REFRESH_COOKIE_NAME)?.value;
}

export function isSessionOnly(req: NextRequest): boolean {
  return req.cookies.has(SESSION_ONLY_COOKIE_NAME);
}

export function getUpstreamApiUrl(): string {
  // Server-side env var with a sensible fallback to the same Prism URL the
  // client is configured for.
  return (
    process.env.QBAZAAR_API_URL ??
    process.env.NEXT_PUBLIC_API_URL ??
    'http://localhost:4010'
  );
}
