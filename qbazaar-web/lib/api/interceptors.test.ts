import { AxiosError, AxiosHeaders, type AxiosResponse, type InternalAxiosRequestConfig } from 'axios';
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import type { User } from '@/lib/api/types';
import { api } from './client';
import { installAuthInterceptors } from './interceptors';
import { registerClientNavigator } from '@/lib/navigation/client-navigator';
import { useAuthStore } from '@/store/auth';

type RejectedHandler = (error: AxiosError) => Promise<unknown>;

let onResponseError: RejectedHandler;

function apiError(status: number, code: string): AxiosError {
  const config = { headers: new AxiosHeaders(), url: '/api/v1/conversations' } as InternalAxiosRequestConfig;
  const response = {
    status,
    statusText: '',
    headers: {},
    config,
    data: { success: false, error: { code, message_key: '', message: '', details: null } },
  } as AxiosResponse;
  return new AxiosError('Request failed', 'ERR_BAD_REQUEST', config, null, response);
}

beforeAll(() => {
  installAuthInterceptors();
  const handlers = (
    api.interceptors.response as unknown as { handlers: { rejected: RejectedHandler }[] }
  ).handlers;
  onResponseError = handlers[handlers.length - 1].rejected;
});

describe('phone verification interceptor', () => {
  const push = vi.fn();
  let unregister: () => void;

  beforeEach(() => {
    window.history.replaceState(null, '', '/ads/42?tab=info');
    useAuthStore.setState({
      user: { id: 'u1', phone_verified: true } as User,
      accessToken: 'AT',
      isHydrated: true,
    });
    unregister = registerClientNavigator(push);
  });

  afterEach(() => {
    unregister();
    push.mockReset();
  });

  it('sends AUTH_003 to phone verification with the current path and marks the user unverified', async () => {
    const error = apiError(403, 'AUTH_003');

    await expect(onResponseError(error)).rejects.toBe(error);

    expect(push).toHaveBeenCalledWith(
      `/account/verification?continue=${encodeURIComponent('/ads/42?tab=info')}`,
    );
    expect(useAuthStore.getState().user?.phone_verified).toBe(false);
  });

  it('does not navigate again while already on the verification flow', async () => {
    window.history.replaceState(null, '', '/account/verification?continue=%2Fpost-ad');

    await expect(onResponseError(apiError(403, 'AUTH_003'))).rejects.toBeDefined();

    expect(push).not.toHaveBeenCalled();
  });

  it('leaves other 403 errors to the caller', async () => {
    const error = apiError(403, 'AUTH_002');

    await expect(onResponseError(error)).rejects.toBe(error);

    expect(push).not.toHaveBeenCalled();
    expect(useAuthStore.getState().user?.phone_verified).toBe(true);
  });
});
