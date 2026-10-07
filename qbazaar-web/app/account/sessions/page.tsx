'use client';

/**
 * FE-2.4 — Active sessions. No frame: built from the Account Settings rows of
 * 394:9270, one row per session with a ghost "Sign out" and a confirmation.
 */
import { useQuery } from '@tanstack/react-query';

import { PanelState } from '@/components/account/PanelState';
import { SessionsList } from '@/components/account/SessionsList';
import { SettingsPanel } from '@/components/account/SettingsPanel';
import { t } from '@/lib/i18n/messages';
import { listSessions } from '@/lib/api/account';

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
    <SettingsPanel title={t('account.sessions.title')} description={t('account.sessions.subtitle')}>
      {isLoading || error ? <PanelState loading={isLoading} /> : <SessionsList sessions={sessions} />}
    </SettingsPanel>
  );
}
