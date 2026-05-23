/**
 * POST /api/auth/refresh
 *
 * Reads the HTTP-only refresh-token cookie, calls the upstream
 * `POST /api/v1/auth/refresh` on Laravel (or Prism in dev), rotates the cookie
 * with the new refresh token, and returns ONLY the new access token to the
 * client. The refresh token itself never crosses the JS boundary.
 */
import { NextResponse, type NextRequest } from 'next/server';
import {
  REFRESH_COOKIE_NAME,
  getUpstreamApiUrl,
  readRefreshCookie,
  refreshCookieOptions,
} from '@/lib/api/refresh-cookie';

interface UpstreamAuthResponse {
  success: true;
  data: {
    user: unknown;
    tokens: {
      access_token: string;
      refresh_token: string;
      token_type: 'Bearer';
      expires_in: number;
    };
  };
}

export async function POST(req: NextRequest): Promise<NextResponse> {
  const refreshToken = readRefreshCookie(req);
  if (!refreshToken) {
    return NextResponse.json(
      {
        success: false,
        error: {
          code: 'AUTH_010',
          message_key: 'errors.auth.token_invalid',
          message: 'Refresh token missing',
          details: null,
        },
      },
      { status: 401 },
    );
  }

  const upstream = await fetch(`${getUpstreamApiUrl()}/api/v1/auth/refresh`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ refresh_token: refreshToken }),
    cache: 'no-store',
  }).catch(() => null);

  if (!upstream || !upstream.ok) {
    const body = upstream
      ? await upstream.json().catch(() => null)
      : {
          success: false,
          error: {
            code: 'NETWORK_ERROR',
            message_key: 'errors.network',
            message: 'Upstream unreachable',
            details: null,
          },
        };
    const status = upstream?.status ?? 502;
    const res = NextResponse.json(body, { status });
    // Wipe the cookie on a hard failure so the client doesn't keep retrying.
    if (status === 401) {
      res.cookies.set(REFRESH_COOKIE_NAME, '', { ...refreshCookieOptions(0) });
    }
    return res;
  }

  const json = (await upstream.json()) as UpstreamAuthResponse;
  const tokens = json.data?.tokens;
  if (!tokens?.access_token || !tokens.refresh_token) {
    return NextResponse.json(
      {
        success: false,
        error: {
          code: 'AUTH_010',
          message_key: 'errors.auth.token_invalid',
          message: 'Malformed refresh response',
          details: null,
        },
      },
      { status: 502 },
    );
  }

  const res = NextResponse.json({
    success: true,
    data: {
      token: {
        access_token: tokens.access_token,
        token_type: tokens.token_type,
        expires_in: tokens.expires_in,
      },
    },
  });

  res.cookies.set(REFRESH_COOKIE_NAME, tokens.refresh_token, refreshCookieOptions());
  return res;
}
