import axios from 'axios';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { refresh } from '@/lib/api/auth';

afterEach(() => vi.restoreAllMocks());

describe('refresh()', () => {
  it('returns null on a 204 (anonymous visitor)', async () => {
    vi.spyOn(axios, 'post').mockResolvedValue({ status: 204, data: '' });
    expect(await refresh()).toBeNull();
  });

  it('returns the token on a 200', async () => {
    const token = { access_token: 'AT', token_type: 'Bearer', expires_in: 900 };
    vi.spyOn(axios, 'post').mockResolvedValue({
      status: 200,
      data: { success: true, data: { token } },
    });
    expect(await refresh()).toEqual({ token });
  });

  it('returns null when the request fails', async () => {
    vi.spyOn(axios, 'post').mockRejectedValue(new Error('network'));
    expect(await refresh()).toBeNull();
  });
});
