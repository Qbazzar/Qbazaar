/**
 * Auth store — Zustand.
 *
 * The access token lives in MEMORY ONLY (never in localStorage), so a hard
 * refresh forces a `/api/auth/refresh` round-trip using the HTTP-only cookie.
 * This is by design: it's the standard hardening against XSS token theft.
 */
import { create } from 'zustand';
import type { User } from '@/lib/api/types';
import { clearFavoritesNonReactive } from './favorites';

export interface AvatarUrls {
  avatar_url: string | null;
  avatar_thumb_url?: string | null;
  avatar_medium_url?: string | null;
}

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
  /**
   * Patch only the avatar fields on the cached user. No-op when the user
   * has not been hydrated yet (avoids spawning a phantom user record).
   */
  setAvatarUrls: (urls: AvatarUrls) => void;
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
  setAvatarUrls: (urls) =>
    set((state) =>
      state.user
        ? {
            user: {
              ...state.user,
              avatar_url: urls.avatar_url,
              avatar_thumb_url: urls.avatar_thumb_url ?? null,
              avatar_medium_url: urls.avatar_medium_url ?? null,
            },
          }
        : state,
    ),
  setLoading: (isLoading) => set({ isLoading }),
  setHydrated: (isHydrated) => set({ isHydrated }),
  clearAuth: () => {
    // Reset cross-cutting per-user caches so a future sign-in doesn't
    // surface the previous account's favorites.
    clearFavoritesNonReactive();
    set({ user: null, accessToken: null, isLoading: false });
  },
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
