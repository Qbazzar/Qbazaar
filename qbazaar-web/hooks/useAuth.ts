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
import { disconnectEcho } from '@/lib/echo/client';
import { disablePush } from '@/lib/push/fcm';

export function useAuth() {
  const user = useAuthStore((s) => s.user);
  const accessToken = useAuthStore((s) => s.accessToken);
  const isLoading = useAuthStore((s) => s.isLoading);
  const isHydrated = useAuthStore((s) => s.isHydrated);
  const setAuth = useAuthStore((s) => s.setAuth);
  const clearAuth = useAuthStore((s) => s.clearAuth);

  const logout = useCallback(async () => {
    // Unregister this browser's push token BEFORE the session is burned —
    // the DELETE needs the still-valid bearer. disablePush() never throws
    // and returns instantly when push was never enabled; the timeout race
    // caps the cost on a flaky network so sign-out is never held hostage.
    await Promise.race([
      disablePush(),
      new Promise((resolve) => setTimeout(resolve, 2500)),
    ]);
    await apiLogout();
    // Tear down the WebSocket so a future sign-in instantiates a fresh
    // Echo client with the next user's auth context.
    disconnectEcho();
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
