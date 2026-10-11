import { renderHook } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const replace = vi.fn();
vi.mock('next/navigation', () => ({
  useRouter: () => ({ replace }),
  usePathname: () => '/account/email-change/result',
}));

import { useAuthStore } from '@/store/auth';

import { useRequireAuth } from './useRequireAuth';

beforeEach(() => {
  vi.clearAllMocks();
  useAuthStore.setState({ user: null, accessToken: null, isHydrated: true });
  window.history.replaceState({}, '', '/account/email-change/result?status=success');
});

describe('useRequireAuth', () => {
  it('keeps the query string of the emailed link in `from`', () => {
    renderHook(() => useRequireAuth());

    expect(replace).toHaveBeenCalledWith(
      `/login?from=${encodeURIComponent('/account/email-change/result?status=success')}`,
    );
  });
});
