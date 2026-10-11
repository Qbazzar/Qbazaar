'use client';

/**
 * Where the API sends the browser after the link of an email change is
 * opened (`/account/email-change/result?status=`): success, a used or stale
 * link (ACCOUNT_002) or an email taken in the meantime (AUTH_007).
 */
import { Suspense, useEffect } from 'react';
import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import { useQueryClient } from '@tanstack/react-query';

import { SettingsPanel, settingsActionClass } from '@/components/account/SettingsPanel';
import { getAccountProfile } from '@/lib/api/account';
import { t } from '@/lib/i18n/messages';
import { useAuthStore } from '@/store/auth';

const RESULTS = ['success', 'ACCOUNT_002', 'AUTH_007'] as const;
type Result = (typeof RESULTS)[number];

export default function EmailChangeResultPage() {
  return (
    <Suspense fallback={null}>
      <EmailChangeResult />
    </Suspense>
  );
}

function EmailChangeResult() {
  const search = useSearchParams();
  const queryClient = useQueryClient();
  const user = useAuthStore((s) => s.user);
  const setUser = useAuthStore((s) => s.setUser);
  const status = search.get('status');
  const result: Result = RESULTS.find((value) => value === status) ?? 'ACCOUNT_002';
  const succeeded = result === 'success';
  const userId = user?.id;

  useEffect(() => {
    if (!succeeded || !userId) return;
    void getAccountProfile().then((profile) => {
      const current = useAuthStore.getState().user;
      if (current) setUser({ ...current, email: profile.email, email_verified: profile.email_verified });
      void queryClient.invalidateQueries({ queryKey: ['account'] });
    });
  }, [queryClient, setUser, succeeded, userId]);

  return (
    <SettingsPanel
      title={t(`account.contact.result.${result}.title`)}
      description={t(`account.contact.result.${result}.body`)}
    >
      <Link href="/account/security" className={`${settingsActionClass} self-start`}>
        {t('account.contact.result.back')}
      </Link>
    </SettingsPanel>
  );
}
