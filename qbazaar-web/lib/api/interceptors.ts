/**
 * Axios interceptors for the shared `api` instance.
 *
 * Request:  inject the in-memory `Authorization: Bearer <accessToken>` header.
 * Response: on a single 401, try to refresh once (mutex-guarded so concurrent
 *           failing requests don't stampede the refresh endpoint), then retry
 *           the original request with the new token. A second 401 (or a failed
 *           refresh) hard-clears the store and redirects to `/login`.
 */
import type {
  AxiosError,
  AxiosResponse,
  InternalAxiosRequestConfig,
} from 'axios';
import { api } from './client';
import { refresh } from './auth';
import {
  clearAuthNonReactive,
  getAccessToken,
  setAccessTokenNonReactive,
} from '@/store/auth';

type RetriableConfig = InternalAxiosRequestConfig & {
  _retried?: boolean;
  _skipAuth?: boolean;
};

let installed = false;

// Refresh-coordination state.
let refreshInFlight: Promise<string | null> | null = null;

// Endpoints that explicitly should NOT carry a bearer token / trigger refresh.
const AUTH_OPEN_PATHS = [
  '/api/v1/auth/login',
  '/api/v1/auth/register',
  '/api/v1/auth/refresh',
  '/api/v1/auth/forgot-password',
  '/api/v1/auth/reset-password',
  '/api/v1/auth/send-otp',
  '/api/v1/auth/verify-otp',
  '/api/v1/auth/resend-otp',
];

function isOpenAuthPath(url: string | undefined): boolean {
  if (!url) return false;
  return AUTH_OPEN_PATHS.some((p) => url.includes(p));
}

async function runRefresh(): Promise<string | null> {
  if (!refreshInFlight) {
    refreshInFlight = (async () => {
      const result = await refresh();
      if (!result) return null;
      setAccessTokenNonReactive(result.token.access_token);
      return result.token.access_token;
    })().finally(() => {
      // Allow next refresh attempt only after this one settles.
      refreshInFlight = null;
    });
  }
  return refreshInFlight;
}

function redirectToLogin(): void {
  // Only redirect in the browser; on the server we just let the error bubble.
  if (typeof window === 'undefined') return;
  const target = window.location.pathname + window.location.search;
  if (!window.location.pathname.startsWith('/login')) {
    const encoded = encodeURIComponent(target);
    window.location.assign(`/login?from=${encoded}`);
  }
}

export function installAuthInterceptors(): void {
  if (installed) return;
  installed = true;

  api.interceptors.request.use((config: InternalAxiosRequestConfig) => {
    const cfg = config as RetriableConfig;
    if (cfg._skipAuth) return cfg;
    const token = getAccessToken();
    if (token && !isOpenAuthPath(cfg.url)) {
      cfg.headers = cfg.headers ?? {};
      cfg.headers.Authorization = `Bearer ${token}`;
    }
    return cfg;
  });

  api.interceptors.response.use(
    (response: AxiosResponse) => response,
    async (error: AxiosError) => {
      const original = error.config as RetriableConfig | undefined;
      const status = error.response?.status;

      // Only intercept genuine 401s on protected endpoints we haven't already retried.
      if (
        !original ||
        status !== 401 ||
        original._retried ||
        isOpenAuthPath(original.url)
      ) {
        return Promise.reject(error);
      }

      original._retried = true;
      const newToken = await runRefresh();

      if (!newToken) {
        clearAuthNonReactive();
        redirectToLogin();
        return Promise.reject(error);
      }

      original.headers = original.headers ?? {};
      original.headers.Authorization = `Bearer ${newToken}`;
      return api.request(original);
    },
  );
}
