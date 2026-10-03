/**
 * Cloudflare Turnstile plumbing for <Turnstile>: the site key, a one-time
 * script loader and the token queue the forms await before submitting.
 */
import { ApiClientError } from '@/lib/api/auth';
import { AuthErrorCode } from '@/lib/api/types';

export const TURNSTILE_SITE_KEY = process.env.NEXT_PUBLIC_TURNSTILE_SITE_KEY ?? '';

const SCRIPT_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

export interface TurnstileRenderOptions {
  sitekey: string;
  callback: (token: string) => void;
  'error-callback': () => void;
  'expired-callback': () => void;
  'timeout-callback': () => void;
  theme: 'light' | 'dark' | 'auto';
  language: string;
  appearance: 'always' | 'execute' | 'interaction-only';
  size: 'normal' | 'flexible' | 'compact';
}

export interface TurnstileApi {
  render(container: HTMLElement, options: TurnstileRenderOptions): string | undefined;
  reset(widgetId: string): void;
  remove(widgetId: string): void;
}

declare global {
  interface Window {
    turnstile?: TurnstileApi;
  }
}

let scriptPromise: Promise<TurnstileApi> | null = null;

export function loadTurnstile(): Promise<TurnstileApi> {
  if (window.turnstile) return Promise.resolve(window.turnstile);

  scriptPromise ??= new Promise<TurnstileApi>((resolve, reject) => {
    const script = document.createElement('script');
    script.src = SCRIPT_URL;
    script.async = true;
    script.onload = () => {
      if (window.turnstile) resolve(window.turnstile);
      else reject(new Error('Turnstile did not initialise'));
    };
    script.onerror = () => {
      // Let the next mount retry instead of caching the failure.
      scriptPromise = null;
      script.remove();
      reject(new Error('Turnstile script failed to load'));
    };
    document.head.appendChild(script);
  });

  return scriptPromise;
}

export function turnstileFailedError(): ApiClientError {
  return new ApiClientError({
    status: 0,
    code: AuthErrorCode.TurnstileFailed,
    messageKey: 'errors.turnstile.failed',
    message: 'The security check failed. Please try again.',
  });
}

interface Waiter {
  resolve: (token: string) => void;
  reject: (err: unknown) => void;
}

/**
 * Holds the current widget token. `next()` resolves right away when a token is
 * ready, otherwise once the widget produces one, so a quick submit simply
 * waits for the challenge instead of going out without a token. A failure
 * sticks until the next token or `clear()`, so later submits reject at once
 * rather than waiting on a widget that will never answer.
 */
export function createTokenQueue() {
  let token: string | undefined;
  let failure: unknown;
  let waiters: Waiter[] = [];

  const settle = (fn: (w: Waiter) => void) => {
    const pending = waiters;
    waiters = [];
    pending.forEach(fn);
  };

  return {
    next(): Promise<string> {
      if (token) return Promise.resolve(token);
      if (failure) return Promise.reject(failure);
      return new Promise((resolve, reject) => waiters.push({ resolve, reject }));
    },
    set(value: string) {
      token = value;
      failure = undefined;
      settle((w) => w.resolve(value));
    },
    clear() {
      token = undefined;
      failure = undefined;
    },
    fail(err: unknown) {
      token = undefined;
      failure = err;
      settle((w) => w.reject(err));
    },
  };
}
