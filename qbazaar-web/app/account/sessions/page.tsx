'use client';

/**
 * FE-2.4 — Active sessions page.
 */
import { useQuery } from '@tanstack/react-query';
import { Loader2Icon } from 'lucide-react';

import { t } from '@/lib/i18n/messages';
import { listSessions } from '@/lib/api/account';
import { SessionsList } from '@/components/account/SessionsList';

export default function AccountSessionsPage() {
  const {
    data: sessions = [],
    isLoading,
    error,
  } = useQuery({
    queryKey: ['account', 'sessions'],
    queryFn: listSessions,
  });

  return (
    <section className="space-y-6">
      <header className="space-y-1.5">
        <h1 className="font-display text-3xl tracking-tight sm:text-4xl">
          {t('account.sessions.title')}
        </h1>
        <p className="text-muted-foreground text-sm">
          {t('account.sessions.subtitle')}
        </p>
      </header>

      <div className="bg-card ring-foreground/10 rounded-2xl p-4 ring-1 sm:p-5">
        {isLoading ? (
          <div
            className="flex justify-center py-10"
            role="status"
            aria-live="polite"
          >
            <Loader2Icon
              className="text-muted-foreground size-5 animate-spin"
              aria-hidden
            />
          </div>
        ) : error ? (
          <p className="text-destructive p-4 text-sm" role="alert">
            {t('auth.errors.network')}
          </p>
        ) : (
          <SessionsList sessions={sessions} />
        )}
      </div>
    </section>
  );
}
