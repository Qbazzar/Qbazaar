'use client';

/**
 * FE-2.3 — Account settings (394:9270): verified phone, email and password
 * rows. "Edit" on the password opens the change-password dialog (411:9755).
 */
import { useState } from 'react';
import Link from 'next/link';

import { Modal } from '@/components/design-system/Modal';
import { PasswordChangeForm } from '@/components/account/PasswordChangeForm';
import { SettingsList, SettingsPanel, SettingsRow, settingsActionClass } from '@/components/account/SettingsPanel';
import { VerifiedBadge } from '@/components/account/VerifiedBadge';
import { maskPhone } from '@/components/account/format';
import { useAuth } from '@/hooks/useAuth';
import { PHONE_VERIFICATION_PATH } from '@/lib/auth/phone-gate';
import { t } from '@/lib/i18n/messages';

export default function AccountSecurityPage() {
  const { user } = useAuth();
  const [editingPassword, setEditingPassword] = useState(false);

  if (!user) return null;

  const verifyLink = (
    <Link href={PHONE_VERIFICATION_PATH} className={settingsActionClass}>
      {t('account.security.verify')}
    </Link>
  );

  return (
    <SettingsPanel title={t('account.nav.account_settings')} description={t('account.security.settings_subtitle')}>
      <SettingsList>
        <SettingsRow
          label={t(user.phone_verified ? 'account.security.verified_phone' : 'account.security.phone')}
          value={<span dir="ltr">{maskPhone(user.phone)}</span>}
          action={user.phone_verified ? <VerifiedBadge /> : verifyLink}
        />
        <SettingsRow
          label={t('account.security.email')}
          value={
            <span dir="ltr" className="break-all">
              {user.email}
            </span>
          }
          action={user.email_verified ? <VerifiedBadge /> : verifyLink}
        />
        <SettingsRow
          label={t('account.security.password')}
          value={<span aria-hidden="true">••••••••••••</span>}
          action={
            <button type="button" onClick={() => setEditingPassword(true)} className={settingsActionClass}>
              {t('common.edit')}
              <span className="sr-only"> {t('account.security.password')}</span>
            </button>
          }
        />
      </SettingsList>

      <Modal
        open={editingPassword}
        onOpenChange={setEditingPassword}
        title={t('account.security.edit_password')}
        className="max-w-[668px]"
      >
        <PasswordChangeForm onDone={() => setEditingPassword(false)} onCancel={() => setEditingPassword(false)} />
      </Modal>
    </SettingsPanel>
  );
}
