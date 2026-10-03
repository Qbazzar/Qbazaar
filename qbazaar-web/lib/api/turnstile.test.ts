import { afterEach, describe, expect, it, vi } from 'vitest';

import { api } from './client';
import { forgotPassword, register, resendOtp, sendOtp } from './auth';
import { createTicket } from './support';
import { TURNSTILE_HEADER, withTurnstile } from './turnstile';
import type { RegisterRequest } from './types';

afterEach(() => {
  vi.restoreAllMocks();
});

describe('withTurnstile', () => {
  it('adds the X-Turnstile-Token header when a token is given', () => {
    expect(withTurnstile('tok')).toEqual({ headers: { 'X-Turnstile-Token': 'tok' } });
  });

  it('adds nothing while Turnstile is disabled', () => {
    expect(withTurnstile(undefined)).toEqual({});
    expect(withTurnstile('')).toEqual({});
  });
});

describe('guarded endpoints', () => {
  function mockPost(data: unknown = {}) {
    return vi.spyOn(api, 'post').mockResolvedValue({ data: { success: true, data } });
  }

  const sentHeaders = (spy: ReturnType<typeof mockPost>) =>
    (spy.mock.calls[0][2] as { headers?: Record<string, string> } | undefined)?.headers;

  it.each([
    ['send-otp', () => sendOtp({ phone: '+97455555555' }, 'tok')],
    ['resend-otp', () => resendOtp({ phone: '+97455555555' }, 'tok')],
    ['forgot-password', () => forgotPassword({ email: 'a@b.qa' }, 'tok')],
    ['support ticket', () => createTicket({ subject: 's', category: 'general', body: 'b' }, 'tok')],
  ])('%s sends the token header', async (_name, call) => {
    const spy = mockPost({ can_resend_in: 60, expires_in: 300 });
    await call();
    expect(sentHeaders(spy)).toEqual({ [TURNSTILE_HEADER]: 'tok' });
  });

  it('register sends the token header', async () => {
    const spy = mockPost({ user: {}, tokens: { access_token: 'a', refresh_token: 'r' } });
    // persistRefreshToken posts through the bare axios instance; keep it offline.
    const axios = (await import('axios')).default;
    vi.spyOn(axios, 'post').mockResolvedValue({});

    await register({} as RegisterRequest, 'tok');

    expect(sentHeaders(spy)).toEqual({ [TURNSTILE_HEADER]: 'tok' });
  });

  it('sends no header without a token', async () => {
    const spy = mockPost();
    await forgotPassword({ email: 'a@b.qa' });
    expect(sentHeaders(spy)).toBeUndefined();
  });
});
