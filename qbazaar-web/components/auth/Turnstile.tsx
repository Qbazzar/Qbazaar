'use client';

import { useEffect, useImperativeHandle, useRef, useState, type Ref } from 'react';

import { getLocale } from '@/lib/i18n/locale';
import {
  TURNSTILE_SITE_KEY,
  createTokenQueue,
  loadTurnstile,
  turnstileFailedError,
  type TurnstileApi,
} from '@/lib/turnstile';

export interface TurnstileHandle {
  /** Token for the next request, or undefined while Turnstile is disabled. */
  getToken(): Promise<string | undefined>;
  /** Tokens are single-use: call after every request to fetch a fresh one. */
  reset(): void;
}

interface TurnstileProps {
  ref: Ref<TurnstileHandle>;
  siteKey?: string;
  className?: string;
}

/**
 * Cloudflare Turnstile in managed, interaction-only mode: invisible unless
 * Cloudflare decides the visitor must click. Renders nothing without a site
 * key, so forms behave exactly as before while the API switch is off.
 */
export function Turnstile({ ref, siteKey = TURNSTILE_SITE_KEY, className }: TurnstileProps) {
  const containerRef = useRef<HTMLDivElement>(null);
  const apiRef = useRef<TurnstileApi | null>(null);
  const widgetIdRef = useRef<string | undefined>(undefined);
  const [queue] = useState(createTokenQueue);

  useImperativeHandle(
    ref,
    () => ({
      getToken: () => (siteKey ? queue.next() : Promise.resolve(undefined)),
      reset: () => {
        // Without a widget (script blocked) keep the failure so submits still report it.
        if (!apiRef.current || !widgetIdRef.current) return;
        queue.clear();
        apiRef.current.reset(widgetIdRef.current);
      },
    }),
    [queue, siteKey],
  );

  useEffect(() => {
    if (!siteKey) return;
    let cancelled = false;

    loadTurnstile()
      .then((api) => {
        if (cancelled || !containerRef.current) return;
        apiRef.current = api;
        widgetIdRef.current = api.render(containerRef.current, {
          sitekey: siteKey,
          callback: (token) => queue.set(token),
          'error-callback': () => queue.fail(turnstileFailedError()),
          // The widget refreshes itself after these; drop the stale token meanwhile.
          'expired-callback': () => queue.clear(),
          'timeout-callback': () => queue.clear(),
          theme: 'light',
          language: getLocale(),
          appearance: 'interaction-only',
          size: 'flexible',
        });
      })
      .catch(() => queue.fail(turnstileFailedError()));

    return () => {
      cancelled = true;
      if (apiRef.current && widgetIdRef.current) {
        apiRef.current.remove(widgetIdRef.current);
      }
      apiRef.current = null;
      widgetIdRef.current = undefined;
      queue.clear();
    };
  }, [queue, siteKey]);

  if (!siteKey) return null;
  return <div ref={containerRef} className={className} />;
}
