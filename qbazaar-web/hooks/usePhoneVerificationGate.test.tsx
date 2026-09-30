import { act, renderHook } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { User } from '@/lib/api/types';

const push = vi.fn();
vi.mock('next/navigation', () => ({ useRouter: () => ({ push }) }));
vi.mock('sonner', () => ({ toast: { info: vi.fn() } }));
vi.mock('@/lib/api/auth', () => ({ logout: vi.fn() }));
vi.mock('@/lib/echo/client', () => ({ disconnectEcho: vi.fn() }));
vi.mock('@/lib/push/fcm', () => ({ disablePush: vi.fn() }));

import { toast } from 'sonner';
import { usePhoneVerificationGate } from './usePhoneVerificationGate';
import { useAuthStore } from '@/store/auth';

const user = { id: 'u1', phone: '+97455123456' } as User;

function signIn(phoneVerified: boolean) {
  useAuthStore.setState({
    user: { ...user, phone_verified: phoneVerified },
    accessToken: 'AT',
    isHydrated: true,
  });
}

beforeEach(() => {
  useAuthStore.setState({ user: null, accessToken: null, isHydrated: true });
  vi.clearAllMocks();
});

describe('usePhoneVerificationGate', () => {
  it('sends guests to login first', () => {
    const { result } = renderHook(() => usePhoneVerificationGate());

    expect(result.current.status).toBe('guest');
    expect(result.current.ensureVerifiedPhone('/ads/9')).toBe(false);
    expect(push).toHaveBeenCalledWith('/login?from=%2Fads%2F9');
  });

  it('sends signed-in users without a verified phone to verification', () => {
    signIn(false);
    const { result } = renderHook(() => usePhoneVerificationGate());

    expect(result.current.ensureVerifiedPhone('/ads/9')).toBe(false);
    expect(push).toHaveBeenCalledWith('/account/verification?continue=%2Fads%2F9');
    expect(toast.info).toHaveBeenCalledOnce();
  });

  it('lets verified users through without navigating', () => {
    signIn(true);
    const { result } = renderHook(() => usePhoneVerificationGate());

    expect(result.current.ensureVerifiedPhone('/ads/9')).toBe(true);
    expect(push).not.toHaveBeenCalled();
  });

  it('opens the gate as soon as the cached user is marked verified', () => {
    signIn(false);
    const { result } = renderHook(() => usePhoneVerificationGate());
    expect(result.current.status).toBe('unverified');

    act(() => useAuthStore.getState().setPhoneVerified(true));

    expect(result.current.status).toBe('verified');
  });

  it('holds the action while the session is still loading', () => {
    useAuthStore.setState({ isHydrated: false });
    const { result } = renderHook(() => usePhoneVerificationGate());

    expect(result.current.ensureVerifiedPhone('/ads/9')).toBe(false);
    expect(push).not.toHaveBeenCalled();
  });
});
