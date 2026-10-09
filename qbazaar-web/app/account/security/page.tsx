'use client';

/**
 * FE-2.3: Account Settings of account.html (394:9270). Phone, email and
 * password rows, each with "Edit" opening its dialog, then "Your Activity".
 * "Active sessions" has no place in the design, so it is one more row here.
 */
import { useState } from 'react';
import Link from 'next/link';

import { AccountDialog } from '@/components/account/AccountDialog';
import { EditEmailDialog, EditPhoneDialog } from '@/components/account/ContactChangeDialogs';
import { PasswordChangeForm } from '@/components/account/PasswordChangeForm';
import { SettingsList, SettingsPanel, SettingsRow, settingsActionClass } from '@/components/account/SettingsPanel';
import { maskPhone } from '@/components/account/format';
import { useAuth } from '@/hooks/useAuth';
import { PHONE_VERIFICATION_PATH } from '@/lib/auth/phone-gate';
import { useAccountSummaryQuery } from '@/lib/queries/account';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';

type Dialog = 'phone' | 'email' | 'password';

export default function AccountSecurityPage() {
  const { user } = useAuth();
  const { data: summary } = useAccountSummaryQuery();
  const [dialog, setDialog] = useState<Dialog | null>(null);

  if (!user) return null;

  const editButton = (target: Dialog, labelKey: string) => (
    <button type="button" onClick={() => setDialog(target)} className={settingsActionClass}>
      {t('common.edit')}
      <span className="sr-only"> {t(labelKey)}</span>
    </button>
  );
  const verifyLink = (
    <Link href={PHONE_VERIFICATION_PATH} className={settingsActionClass}>
      {t('account.security.verify')}
    </Link>
  );
  const closeDialog = (open: boolean) => {
    if (!open) setDialog(null);
  };
  const liveAds = summary?.ads_by_status.active;

  return (
    <SettingsPanel title={t('account.nav.account_settings')} description={t('account.security.settings_subtitle')}>
      <SettingsList>
        <SettingsRow
          label={t(user.phone_verified ? 'account.security.verified_phone' : 'account.security.phone')}
          value={<span dir="ltr">{maskPhone(user.phone)}</span>}
          action={
            <>
              {user.phone_verified ? null : verifyLink}
              {editButton('phone', 'account.security.phone')}
            </>
          }
        />
        <SettingsRow
          label={t('account.security.email')}
          value={
            <span dir="ltr" className="break-all">
              {user.email}
            </span>
          }
          action={
            <>
              {user.email_verified ? null : verifyLink}
              {editButton('email', 'account.security.email')}
            </>
          }
        />
        <SettingsRow
          label={t('account.security.password')}
          value={
            <span aria-hidden="true" className="tracking-[2px]">
              ******************
            </span>
          }
          action={editButton('password', 'account.security.password')}
        />
        <SettingsRow
          label={t('account.security.activity_label')}
          value={
            <span className="leading-[1.5]">
              {liveAds === undefined ? '…' : tPlural('account.security.activity_value', liveAds)}
            </span>
          }
        />
        <SettingsRow
          label={t('account.nav.sessions')}
          value={t('account.security.sessions_value')}
          action={
            <Link href="/account/sessions" className={settingsActionClass}>
              {t('account.security.sessions_manage')}
            </Link>
          }
        />
      </SettingsList>

      <EditPhoneDialog open={dialog === 'phone'} onOpenChange={closeDialog} />
      <EditEmailDialog open={dialog === 'email'} onOpenChange={closeDialog} />
      <AccountDialog
        open={dialog === 'password'}
        onOpenChange={closeDialog}
        title={t('account.security.edit_password')}
      >
        <PasswordChangeForm onDone={() => setDialog(null)} onCancel={() => setDialog(null)} />
      </AccountDialog>
    </SettingsPanel>
  );
}
