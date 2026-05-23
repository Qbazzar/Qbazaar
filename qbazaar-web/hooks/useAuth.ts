'use client';

/**
 * useAuth — surface the Zustand auth state to React components.
 *
 * Wave 1 keeps this intentionally thin: the actual hydration (calling
 * `/api/auth/refresh` on first paint, then `/api/v1/auth/me` to fetch the user)
 * lands in Wave 2 together with the `me` endpoint. For now this hook exposes
 * the in-memory store + a `logout()` helper.
 */
import { useCallback } from 'react';
import { useAuthStore } from '@/store/auth';
import { logout as apiLogout } from '@/lib/api/auth';

export function useAuth() {
  const user = useAuthStore((s) => s.user);
  const accessToken = useAuthStore((s) => s.accessToken);
  const isLoading = useAuthStore((s) => s.isLoading);
  const isHydrated = useAuthStore((s) => s.isHydrated);
  const setAuth = useAuthStore((s) => s.setAuth);
  const clearAuth = useAuthStore((s) => s.clearAuth);

  const logout = useCallback(async () => {
    await apiLogout();
    clearAuth();
  }, [clearAuth]);

  return {
    user,
    accessToken,
    isAuthenticated: Boolean(user && accessToken),
    isLoading,
    isHydrated,
    setAuth,
    clearAuth,
    logout,
  };
}
