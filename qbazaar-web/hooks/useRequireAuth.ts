'use client';

/**
 * useRequireAuth — client-side route guard.
 *
 * Reads the in-memory auth store and redirects unauthenticated users to
 * `/login?from=<encoded current path>`. Until store hydration completes
 * the hook returns `{ user: null, isLoading: true }` so callers can render
 * a loading skeleton instead of flashing the page.
 *
 * Wave 2 will replace this with a server-side guard once the access token
 * hydration round-trip moves to the route handler.
 */
import { useEffect } from 'react';
import { usePathname, useRouter } from 'next/navigation';
import { useAuthStore } from '@/store/auth';
import { loginHref } from '@/lib/auth/phone-gate';
import { currentLocationPath } from '@/lib/navigation/safe-return-to';

export interface UseRequireAuthResult {
  user: ReturnType<typeof useAuthStore.getState>['user'];
  isLoading: boolean;
}

export function useRequireAuth(): UseRequireAuthResult {
  const router = useRouter();
  const pathname = usePathname();
  const user = useAuthStore((s) => s.user);
  const accessToken = useAuthStore((s) => s.accessToken);
  const isHydrated = useAuthStore((s) => s.isHydrated);

  const isAuthenticated = Boolean(user && accessToken);

  useEffect(() => {
    // Wait for hydration before deciding — otherwise we'd kick the user out
    // every time they refresh the page.
    if (!isHydrated) return;
    if (!isAuthenticated) {
      router.replace(loginHref(currentLocationPath()));
    }
  }, [isAuthenticated, isHydrated, pathname, router]);

  return {
    user,
    isLoading: !isHydrated || !isAuthenticated,
  };
}
