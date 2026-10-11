// @vitest-environment node
import { NextRequest } from 'next/server';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { POST } from './route';

afterEach(() => vi.unstubAllGlobals());

function requestWith(cookie?: string): NextRequest {
  return new NextRequest('http://localhost/api/auth/refresh', {
    method: 'POST',
    headers: cookie ? { cookie } : {},
  });
}

describe('POST /api/auth/refresh', () => {
  it('answers 204 without calling upstream when there is no refresh cookie', async () => {
    const fetchSpy = vi.fn();
    vi.stubGlobal('fetch', fetchSpy);

    const res = await POST(requestWith());

    expect(res.status).toBe(204);
    expect(fetchSpy).not.toHaveBeenCalled();
  });

  it('rotates the cookie and returns only the access token when signed in', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(
        new Response(
          JSON.stringify({
            success: true,
            data: {
              user: {},
              tokens: {
                access_token: 'AT',
                refresh_token: 'RT2',
                token_type: 'Bearer',
                expires_in: 900,
              },
            },
          }),
          { status: 200 },
        ),
      ),
    );

    const res = await POST(requestWith('qb_refresh_token=RT1'));
    const body = await res.json();

    expect(res.status).toBe(200);
    expect(body.data.token.access_token).toBe('AT');
    expect(JSON.stringify(body)).not.toContain('RT2');
    expect(res.cookies.get('qb_refresh_token')?.value).toBe('RT2');
    expect(res.cookies.get('qb_refresh_token')?.maxAge).toBe(60 * 60 * 24 * 30);
  });

  it('keeps a sign-in without "Remember me" a browser-session cookie', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(
        new Response(
          JSON.stringify({
            success: true,
            data: {
              user: {},
              tokens: { access_token: 'AT', refresh_token: 'RT2', token_type: 'Bearer', expires_in: 900 },
            },
          }),
          { status: 200 },
        ),
      ),
    );

    const res = await POST(requestWith('qb_refresh_token=RT1; qb_session_only=1'));

    expect(res.cookies.get('qb_refresh_token')?.value).toBe('RT2');
    expect(res.cookies.get('qb_refresh_token')?.maxAge).toBeUndefined();
  });
});
