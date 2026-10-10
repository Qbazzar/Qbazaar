'use client';

/**
 * SessionsList — renders the authenticated user's active sessions.
 *
 * One Account Settings style row per session (394:9270); the current device
 * carries a badge. "Sign out" asks for confirmation in a modal, then calls
 * `DELETE /account/sessions/{id}`; on success the row drops out via cache
 * invalidation.
 */
import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Loader2, MonitorSmartphone } from 'lucide-react';

import { Badge } from '@/components/design-system/Badge';
import { Button } from '@/components/design-system/Button';
import { showDesignToast } from '@/components/design-system/design-toast';
import { EmptyState } from '@/components/design-system/EmptyState';
import { Icon } from '@/components/design-system/Icon';
import { Modal } from '@/components/design-system/Modal';
import { intlLocale } from '@/lib/i18n/format';
import { getLocale } from '@/lib/i18n/locale';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import { formatRelativeTime } from '@/lib/utils';
import { revokeSession } from '@/lib/api/account';
import { ApiClientError } from '@/lib/api/auth';
import type { UserSession } from '@/lib/api/types';

import { ModalActions } from './ModalActions';
import { SettingsList, SettingsRow } from './SettingsPanel';

export interface SessionsListProps {
  sessions: UserSession[];
}

export function SessionsList({ sessions }: SessionsListProps) {
  const queryClient = useQueryClient();
  const [confirming, setConfirming] = useState<UserSession | null>(null);

  const mutation = useMutation({
    mutationFn: (id: string) => revokeSession(id),
    onSuccess: () => {
      showDesignToast(t('account.sessions.revoke_success'));
      setConfirming(null);
      queryClient.invalidateQueries({ queryKey: ['account', 'sessions'] });
    },
    onError: (err) => {
      if (err instanceof ApiClientError) {
        toast.error(
          translateMaybeKey(`account.errors.${err.code}`) ||
            translateMaybeKey(`auth.errors.${err.code}`) ||
            err.message,
        );
      } else {
        toast.error(t('auth.errors.unknown'));
      }
    },
  });

  if (sessions.length === 0) {
    return (
      <EmptyState
        icon={<Icon icon={MonitorSmartphone} size="lg" />}
        title={t('account.sessions.empty_title')}
        description={t('account.sessions.empty_body')}
      />
    );
  }

  return (
    <>
      <SettingsList>
        {sessions.map((session) => (
          <SettingsRow
            key={session.id}
            icon={<MonitorSmartphone />}
            value={
              <span className="flex flex-wrap items-center gap-2">
                {deviceLabel(session)}
                {session.is_current ? (
                  <Badge tone="brand" size="sm" font="label">
                    {t('account.sessions.current_badge')}
                  </Badge>
                ) : null}
              </span>
            }
            description={<SessionMeta session={session} />}
            action={
              session.is_current ? null : (
                <Button variant="ghost" size="sm" onClick={() => setConfirming(session)}>
                  {t('account.sessions.revoke')}
                  <span className="sr-only"> {deviceLabel(session)}</span>
                </Button>
              )
            }
          />
        ))}
      </SettingsList>

      <Modal
        open={confirming !== null}
        onOpenChange={(open) => {
          if (!open && !mutation.isPending) setConfirming(null);
        }}
        title={t('account.sessions.confirm_title')}
        description={confirming ? t('account.sessions.confirm_body', { device: deviceLabel(confirming) }) : undefined}
      >
        <ModalActions className="mt-2">
          <Button
            size="sm"
            disabled={mutation.isPending}
            onClick={() => confirming && mutation.mutate(confirming.id)}
          >
            {mutation.isPending ? (
              <>
                <Loader2 className="animate-spin" aria-hidden="true" />
                {t('account.sessions.revoking')}
              </>
            ) : (
              t('account.sessions.revoke')
            )}
          </Button>
          <Button variant="muted" size="sm" disabled={mutation.isPending} onClick={() => setConfirming(null)}>
            {t('common.cancel')}
          </Button>
        </ModalActions>
      </Modal>
    </>
  );
}

function deviceLabel(session: UserSession): string {
  const label = session.device_label?.trim();
  return label && label !== 'unknown' ? label : t('account.sessions.unknown_device');
}

function SessionMeta({ session }: { session: UserSession }) {
  const lastUsed = session.last_used_at ?? session.created_at;
  return (
    <span className="flex flex-wrap gap-x-3 gap-y-0.5">
      {session.ip_address ? (
        <span>
          {t('account.sessions.ip_label')} <span dir="ltr">{session.ip_address}</span>
        </span>
      ) : null}
      {lastUsed ? (
        <span>{t('account.sessions.last_used', { when: formatRelativeTime(lastUsed, intlLocale(getLocale())) })}</span>
      ) : null}
    </span>
  );
}
