'use client';

import { useCallback } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';

import { showDesignToast } from '@/components/design-system/design-toast';
import type { AuthResponseData } from '@/lib/api/types';
import { t } from '@/lib/i18n/messages';
import { safeReturnTo } from '@/lib/navigation/safe-return-to';
import { useAuthStore } from '@/store/auth';

/** Stores the new session and returns to the page the visitor came from (`?from=`). */
export function useCompleteSignIn(): (data: AuthResponseData) => void {
  const router = useRouter();
  const search = useSearchParams();
  const setAuth = useAuthStore((s) => s.setAuth);
  const setHydrated = useAuthStore((s) => s.setHydrated);

  return useCallback(
    (data: AuthResponseData) => {
      setAuth({ user: data.user, accessToken: data.tokens.access_token });
      setHydrated(true);
      showDesignToast(t('auth.login.success'));
      router.replace(safeReturnTo(search.get('from')));
    },
    [router, search, setAuth, setHydrated],
  );
}
