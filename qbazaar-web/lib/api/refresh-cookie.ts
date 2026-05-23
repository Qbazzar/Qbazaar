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

// 30 days, matching the contract's refresh-token lifetime.
const REFRESH_COOKIE_MAX_AGE = 60 * 60 * 24 * 30;

export function refreshCookieOptions(maxAge: number = REFRESH_COOKIE_MAX_AGE) {
  return {
    httpOnly: true,
    secure: process.env.NODE_ENV === 'production',
    sameSite: 'lax' as const,
    path: '/',
    maxAge,
  };
}

export function readRefreshCookie(req: NextRequest): string | undefined {
  return req.cookies.get(REFRESH_COOKIE_NAME)?.value;
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
