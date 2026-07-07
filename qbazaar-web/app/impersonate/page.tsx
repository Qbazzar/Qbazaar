'use client';

/**
 * Impersonation landing — consumes the token pair handed off by the admin
 * panel (/manage) in the URL *fragment*, logs the browser in as the target
 * user, and drops them on the account page.
 *
 * The fragment (never sent to the server or logged) carries `access`,
 * `refresh` and `name`. We mirror AuthBootstrap's wiring: persist the refresh
 * cookie, set the in-memory access token, fetch the profile, then setAuth.
 */
import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import axios from 'axios';
import { getAccountProfile } from '@/lib/api/account';
import { setAccessTokenNonReactive, useAuthStore } from '@/store/auth';
import { t } from '@/lib/i18n/messages';

export default function ImpersonatePage() {
  const router = useRouter();
  const [error, setError] = useState(false);

  useEffect(() => {
    const hash = window.location.hash.startsWith('#')
      ? window.location.hash.slice(1)
      : '';
    const params = new URLSearchParams(hash);
    const access = params.get('access');
    const refresh = params.get('refresh');
    const name = params.get('name') ?? '';

    // Wipe the fragment immediately so the tokens don't linger in history.
    window.history.replaceState(null, '', '/impersonate');

    if (!access || !refresh) {
      setError(true);
      return;
    }

    let cancelled = false;

    (async () => {
      try {
        // Persist the refresh token as the HTTP-only cookie (same route the
        // normal login flow uses) so token refresh keeps working afterwards.
        await axios.post(
          '/api/auth/session',
          { refresh_token: refresh },
          { withCredentials: true, headers: { 'Content-Type': 'application/json' } },
        );

        setAccessTokenNonReactive(access);
        const user = await getAccountProfile();
        if (cancelled) return;

        useAuthStore.getState().setAuth({ user, accessToken: access });
        sessionStorage.setItem('qb_impersonating', name || '1');

        router.replace('/account');
      } catch {
        if (!cancelled) setError(true);
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [router]);

  return (
    <main className="flex min-h-[60vh] items-center justify-center px-4">
      <div className="text-center">
        {error ? (
          <>
            <h1 className="empty-state__title">
              {t('common.error', 'حدث خطأ، حاول مرة أخرى')}
            </h1>
            <p className="empty-state__sub mt-2">
              {t('impersonate.failed', 'تعذّر بدء جلسة الانتحال. الرابط ربما انتهت صلاحيته.')}
            </p>
          </>
        ) : (
          <p className="text-ink-500">
            {t('impersonate.loading', 'جارٍ تسجيل الدخول…')}
          </p>
        )}
      </div>
    </main>
  );
}
