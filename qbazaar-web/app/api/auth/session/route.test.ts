// @vitest-environment node
import { NextRequest } from 'next/server';
import { describe, expect, it } from 'vitest';

import { DELETE, POST } from './route';

function save(body: unknown): Promise<Response> {
  return POST(
    new NextRequest('http://localhost/api/auth/session', {
      method: 'POST',
      headers: { 'content-type': 'application/json' },
      body: JSON.stringify(body),
    }),
  );
}

/** The Set-Cookie line of one cookie. */
function setCookie(res: Response, name: string): string {
  return res.headers.getSetCookie().find((line) => line.startsWith(`${name}=`)) ?? '';
}

describe('/api/auth/session', () => {
  it('keeps a remembered sign-in for 30 days', async () => {
    const res = await save({ refresh_token: 'RT', remember: true });

    expect(setCookie(res, 'qb_refresh_token')).toMatch(/^qb_refresh_token=RT;.*Max-Age=2592000/);
    expect(setCookie(res, 'qb_session_only')).toMatch(/Max-Age=0/);
  });

  it('ends a sign-in without "Remember me" when the browser closes', async () => {
    const res = await save({ refresh_token: 'RT', remember: false });

    expect(setCookie(res, 'qb_refresh_token')).toMatch(/^qb_refresh_token=RT;/);
    expect(setCookie(res, 'qb_refresh_token')).not.toMatch(/Max-Age|Expires/);
    expect(setCookie(res, 'qb_session_only')).toMatch(/^qb_session_only=1;/);
    expect(setCookie(res, 'qb_session_only')).not.toMatch(/Max-Age|Expires/);
  });

  it('rejects a body without a token', async () => {
    expect((await save({ remember: false })).status).toBe(400);
  });

  it('clears both cookies on sign-out', async () => {
    const res = await DELETE();

    expect(setCookie(res, 'qb_refresh_token')).toMatch(/Max-Age=0/);
    expect(setCookie(res, 'qb_session_only')).toMatch(/Max-Age=0/);
  });
});
