/**
 * Session cookie management for the refresh token.
 *
 *   POST   /api/auth/session  { refresh_token, remember? }   → set the HTTP-only cookie
 *          (`remember: false` makes it a browser-session cookie)
 *   DELETE /api/auth/session                       → clear the cookie
 *
 * Called by `lib/api/auth.ts` right after a successful register/login (POST)
 * and after a logout (DELETE). The refresh token itself is sourced from the
 * direct call to the upstream auth endpoint, which is why the client briefly
 * holds it before handing it over — that one-shot is unavoidable. From that
 * moment on, no JS code ever touches the refresh token again.
 */
import { NextResponse, type NextRequest } from 'next/server';
import {
  REFRESH_COOKIE_NAME,
  SESSION_ONLY_COOKIE_NAME,
  refreshCookieOptions,
} from '@/lib/api/refresh-cookie';

export async function POST(req: NextRequest): Promise<NextResponse> {
  let body: { refresh_token?: unknown; remember?: unknown } = {};
  try {
    body = await req.json();
  } catch {
    return NextResponse.json({ success: false }, { status: 400 });
  }
  const refreshToken = body.refresh_token;
  if (typeof refreshToken !== 'string' || refreshToken.length === 0) {
    return NextResponse.json({ success: false }, { status: 400 });
  }
  const sessionOnly = body.remember === false;
  const res = NextResponse.json({ success: true, data: { saved: true } });
  res.cookies.set(REFRESH_COOKIE_NAME, refreshToken, refreshCookieOptions(sessionOnly ? 'session' : undefined));
  res.cookies.set(SESSION_ONLY_COOKIE_NAME, sessionOnly ? '1' : '', refreshCookieOptions(sessionOnly ? 'session' : 0));
  return res;
}

export async function DELETE(): Promise<NextResponse> {
  const res = NextResponse.json({ success: true, data: { cleared: true } });
  res.cookies.set(REFRESH_COOKIE_NAME, '', refreshCookieOptions(0));
  res.cookies.set(SESSION_ONLY_COOKIE_NAME, '', refreshCookieOptions(0));
  return res;
}
