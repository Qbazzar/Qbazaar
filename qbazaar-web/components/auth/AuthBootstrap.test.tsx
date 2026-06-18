import { render, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

// Mock the network boundary; exercise the real Zustand store.
vi.mock('@/lib/api/interceptors', () => ({ installAuthInterceptors: vi.fn() }));
vi.mock('@/lib/api/auth', () => ({ refresh: vi.fn() }));
vi.mock('@/lib/api/account', () => ({ getAccountProfile: vi.fn() }));

import { AuthBootstrap } from '@/components/auth/AuthBootstrap';
import { refresh } from '@/lib/api/auth';
import { getAccountProfile } from '@/lib/api/account';
import { useAuthStore } from '@/store/auth';

const mockRefresh = vi.mocked(refresh);
const mockGetProfile = vi.mocked(getAccountProfile);

beforeEach(() => {
  useAuthStore.setState({
    user: null,
    accessToken: null,
    isLoading: false,
    isHydrated: false,
  });
  vi.clearAllMocks();
});

describe('AuthBootstrap', () => {
  it('restores the session from the refresh cookie and marks hydrated', async () => {
    mockRefresh.mockResolvedValue({
      token: { access_token: 'AT', token_type: 'Bearer', expires_in: 900 },
    } as never);
    mockGetProfile.mockResolvedValue({
      id: 'u1',
      full_name: 'Tester',
      email: 't@example.com',
    } as never);

    render(<AuthBootstrap />);

    await waitFor(() => expect(useAuthStore.getState().isHydrated).toBe(true));

    const state = useAuthStore.getState();
    expect(state.accessToken).toBe('AT');
    expect(state.user?.id).toBe('u1');
  });

  it('stays anonymous but still hydrates when there is no refresh token', async () => {
    mockRefresh.mockResolvedValue(null);

    render(<AuthBootstrap />);

    await waitFor(() => expect(useAuthStore.getState().isHydrated).toBe(true));

    expect(useAuthStore.getState().user).toBeNull();
    expect(mockGetProfile).not.toHaveBeenCalled();
  });
});
