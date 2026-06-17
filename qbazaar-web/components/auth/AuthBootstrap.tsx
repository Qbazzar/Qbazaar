'use client';

/**
 * Session bootstrap — side-effect-only component.
 *
 * The access token lives in memory only (see `store/auth.ts`), so on every
 * fresh load / hard refresh we must exchange the HTTP-only refresh cookie for
 * a new access token, then load the user identity. Until this completes the
 * store stays `isHydrated: false` so the header can hold off rendering the
 * signed-out CTA prematurely.
 *
 * Runs exactly once on mount. The `ran` ref guards against React Strict Mode's
 * double-invoke of effects in development.
 */
import { useEffect, useRef } from 'react';
import { installAuthInterceptors } from '@/lib/api/interceptors';
import { refresh } from '@/lib/api/auth';
import { getAccountProfile } from '@/lib/api/account';
import { setAccessTokenNonReactive, useAuthStore } from '@/store/auth';

export function AuthBootstrap() {
  const ran = useRef(false);

  useEffect(() => {
    if (ran.current) return;
    ran.current = true;

    const { setAuth, setHydrated, setLoading, clearAuth } =
      useAuthStore.getState();

    // Idempotent — guarded internally — but ensures the bearer/refresh
    // interceptors exist before the authenticated profile call below.
    installAuthInterceptors();

    void (async () => {
      setLoading(true);
      try {
        const result = await refresh();
        const accessToken = result?.token?.access_token;
        if (!accessToken) {
          clearAuth();
          return;
        }

        setAccessTokenNonReactive(accessToken);
        // `AccountProfile` extends `User`, so it satisfies the store shape.
        const user = await getAccountProfile();
        setAuth({ user, accessToken });
      } catch {
        // An anonymous visitor is valid — never redirect from the bootstrap.
        clearAuth();
      } finally {
        setHydrated(true);
        setLoading(false);
      }
    })();
  }, []);

  return null;
}
