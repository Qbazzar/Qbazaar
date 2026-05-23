/**
 * Auth store — Zustand.
 *
 * The access token lives in MEMORY ONLY (never in localStorage), so a hard
 * refresh forces a `/api/auth/refresh` round-trip using the HTTP-only cookie.
 * This is by design: it's the standard hardening against XSS token theft.
 */
import { create } from 'zustand';
import type { User } from '@/lib/api/types';

export interface AuthState {
  user: User | null;
  accessToken: string | null;
  // `true` while the bootstrap refresh is in flight on first paint.
  isLoading: boolean;
  // True once `useAuth` has attempted hydration at least once.
  isHydrated: boolean;
  setAuth: (params: { user: User; accessToken: string }) => void;
  setAccessToken: (accessToken: string | null) => void;
  setUser: (user: User | null) => void;
  setLoading: (isLoading: boolean) => void;
  setHydrated: (isHydrated: boolean) => void;
  clearAuth: () => void;
}

export const useAuthStore = create<AuthState>((set) => ({
  user: null,
  accessToken: null,
  isLoading: false,
  isHydrated: false,
  setAuth: ({ user, accessToken }) => set({ user, accessToken }),
  setAccessToken: (accessToken) => set({ accessToken }),
  setUser: (user) => set({ user }),
  setLoading: (isLoading) => set({ isLoading }),
  setHydrated: (isHydrated) => set({ isHydrated }),
  clearAuth: () =>
    set({ user: null, accessToken: null, isLoading: false }),
}));

/**
 * Non-reactive accessor used by axios interceptors so they don't subscribe
 * to React's render cycle.
 */
export function getAccessToken(): string | null {
  return useAuthStore.getState().accessToken;
}

export function setAccessTokenNonReactive(token: string | null): void {
  useAuthStore.getState().setAccessToken(token);
}

export function clearAuthNonReactive(): void {
  useAuthStore.getState().clearAuth();
}
