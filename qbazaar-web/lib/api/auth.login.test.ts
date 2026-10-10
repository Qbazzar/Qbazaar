import axios from 'axios';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { api } from '@/lib/api/client';
import { login, verifyNewDevice } from '@/lib/api/auth';

afterEach(() => vi.restoreAllMocks());

const signedIn = {
  success: true,
  data: {
    user: { id: 'u1' },
    tokens: { access_token: 'AT', refresh_token: 'RT', token_type: 'Bearer', expires_in: 900 },
  },
};

describe('login()', () => {
  it('stores the refresh token for the browser session unless remembered', async () => {
    vi.spyOn(api, 'post').mockResolvedValue({ status: 200, data: signedIn });
    const persist = vi.spyOn(axios, 'post').mockResolvedValue({ status: 200, data: {} });

    const result = await login({ identifier: 'a@b.qa', password: 'x' }, { remember: false });

    expect(result).toEqual({ status: 'signed_in', data: signedIn.data });
    expect(persist).toHaveBeenCalledWith('/api/auth/session', { refresh_token: 'RT', remember: false }, expect.anything());
  });

  it('remembers the sign-in by default', async () => {
    vi.spyOn(api, 'post').mockResolvedValue({ status: 200, data: signedIn });
    const persist = vi.spyOn(axios, 'post').mockResolvedValue({ status: 200, data: {} });

    await login({ identifier: 'a@b.qa', password: 'x' });

    expect(persist).toHaveBeenCalledWith('/api/auth/session', { refresh_token: 'RT', remember: true }, expect.anything());
  });

  it('returns the new-device challenge of a 202 without storing anything', async () => {
    const challenge = { challenge_token: 'c'.repeat(64), sent_to: '+974*****345', expires_in: 600, can_resend_in: 60 };
    vi.spyOn(api, 'post').mockResolvedValue({ status: 202, data: { device_verification_required: true, ...challenge } });
    const persist = vi.spyOn(axios, 'post');

    expect(await login({ identifier: 'a@b.qa', password: 'x' })).toEqual({ status: 'device_check', challenge });
    expect(persist).not.toHaveBeenCalled();
  });
});

describe('verifyNewDevice()', () => {
  it('finishes the held sign-in and stores the token with the remember choice', async () => {
    const post = vi.spyOn(api, 'post').mockResolvedValue({ status: 200, data: signedIn });
    const persist = vi.spyOn(axios, 'post').mockResolvedValue({ status: 200, data: {} });

    const data = await verifyNewDevice({ challenge_token: 'tok', code: '123456' }, { remember: false });

    expect(data).toEqual(signedIn.data);
    expect(post).toHaveBeenCalledWith('/api/v1/auth/device/verify', { challenge_token: 'tok', code: '123456' });
    expect(persist).toHaveBeenCalledWith('/api/auth/session', { refresh_token: 'RT', remember: false }, expect.anything());
  });
});
