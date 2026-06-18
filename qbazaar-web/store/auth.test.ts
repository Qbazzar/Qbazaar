import { beforeEach, describe, expect, it } from 'vitest';
import type { User } from '@/lib/api/types';
import {
  clearAuthNonReactive,
  getAccessToken,
  setAccessTokenNonReactive,
  useAuthStore,
} from '@/store/auth';

const fakeUser = { id: 'u1', full_name: 'Tester', email: 't@example.com' } as User;

beforeEach(() => {
  useAuthStore.setState({
    user: null,
    accessToken: null,
    isLoading: false,
    isHydrated: false,
  });
});

describe('auth store', () => {
  it('setAuth populates user + token without touching isHydrated', () => {
    useAuthStore.getState().setAuth({ user: fakeUser, accessToken: 'AT' });

    const state = useAuthStore.getState();
    expect(state.user?.id).toBe('u1');
    expect(state.accessToken).toBe('AT');
    // setAuth alone must not flip hydration — the bootstrap/forms own that.
    expect(state.isHydrated).toBe(false);
  });

  it('getAccessToken / setAccessTokenNonReactive read + write the in-memory token', () => {
    expect(getAccessToken()).toBeNull();
    setAccessTokenNonReactive('AT2');
    expect(getAccessToken()).toBe('AT2');
  });

  it('clearAuth wipes user + token but leaves isHydrated intact', () => {
    useAuthStore.setState({
      user: fakeUser,
      accessToken: 'AT',
      isHydrated: true,
    });

    clearAuthNonReactive();

    const state = useAuthStore.getState();
    expect(state.user).toBeNull();
    expect(state.accessToken).toBeNull();
    // After logout the header must show the login CTA, not a loading state.
    expect(state.isHydrated).toBe(true);
  });

  it('setHydrated toggles the hydration flag', () => {
    useAuthStore.getState().setHydrated(true);
    expect(useAuthStore.getState().isHydrated).toBe(true);
  });
});
