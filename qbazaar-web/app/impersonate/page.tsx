'use client';

/**
 * Impersonation landing: consumes the short-lived access token handed off by
 * the admin panel in the URL *fragment* (never sent to the server or logged),
 * signs the browser in as the target user and opens the account page.
 *
 * No refresh token is issued for impersonation, so any refresh cookie left
 * from the admin's own session is cleared: the borrowed session ends when the
 * access token expires or the page is reloaded.
 *
 * No Figma frame: the status sits in the auth card (736:65208).
 */
import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import axios from 'axios';
import { CircleAlert, LoaderCircle } from 'lucide-react';

import { buttonVariants } from '@/components/design-system/Button';
import { Card } from '@/components/design-system/Card';
import { StateIcon } from '@/components/design-system/StatePanel';
import { getAccountProfile } from '@/lib/api/account';
import { setAccessTokenNonReactive, useAuthStore } from '@/store/auth';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

export default function ImpersonatePage() {
  const router = useRouter();
  const [error, setError] = useState(false);

  useEffect(() => {
    const hash = window.location.hash.startsWith('#')
      ? window.location.hash.slice(1)
      : '';
    const params = new URLSearchParams(hash);
    const access = params.get('access');
    const name = params.get('name') ?? '';

    // Wipe the fragment immediately so the tokens don't linger in history.
    window.history.replaceState(null, '', '/impersonate');

    if (!access) {
      setError(true);
      return;
    }

    let cancelled = false;

    (async () => {
      try {
        await axios.delete('/api/auth/session', { withCredentials: true });

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
    <main className="bg-qb-page px-qb-gutter py-12 font-qb text-qb-ink qb-tablet:py-16">
      <Card large elevated className="mx-auto flex max-w-[714px] flex-col items-center px-6 py-12 text-center qb-tablet:px-10">
        <div
          aria-hidden="true"
          className="mb-6 flex size-[76px] items-center justify-center rounded-qb-xl border border-qb-line bg-qb-surface text-qb-brand shadow-qb-brand qb-tablet:size-[92px] qb-tablet:rounded-qb-2xl"
        >
          <StateIcon
            icon={error ? CircleAlert : LoaderCircle}
            tone={error ? 'muted' : 'brand'}
            className={error ? undefined : 'motion-safe:animate-spin'}
          />
        </div>
        <div role="status">
          <h1 className="font-qb text-qb-h5 font-semibold tracking-normal text-qb-ink qb-tablet:text-qb-h3">
            {error ? t('impersonate.failed_title') : t('impersonate.loading')}
          </h1>
          {error ? (
            <p className="mt-2 text-qb-body text-qb-ink-muted">{t('impersonate.failed')}</p>
          ) : null}
        </div>
        {error ? (
          <Link href="/" className={cn(buttonVariants({ size: 'sm' }), 'mt-6')}>
            {t('errors.back_home')}
          </Link>
        ) : null}
      </Card>
    </main>
  );
}
