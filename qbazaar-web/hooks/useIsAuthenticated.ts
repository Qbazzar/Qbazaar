'use client';

import { useAuthStore } from '@/store/auth';

/** True once the session has a user and an access token; gates the account queries. */
export function useIsAuthenticated(): boolean {
  return useAuthStore((s) => Boolean(s.user && s.accessToken));
}
