'use client';

/**
 * Profile Settings panel of account.html (393:8828): the photo and "Change
 * Profile photo", then "Personal Information" with the name and the delivery
 * address. "Edit" on the name opens "Change Profile Name" (401:11108); on the
 * address the address dialogs.
 */
import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';

import { getAccountProfile, listAddresses } from '@/lib/api/account';
import { t } from '@/lib/i18n/messages';

import { AccountDialog } from './AccountDialog';
import { ADDRESSES_QUERY_KEY, AddressDialog, addressSummary } from './AddressDialog';
import { AvatarUploader } from './AvatarUploader';
import { PanelState } from './PanelState';
import { ProfileForm } from './ProfileForm';
import { SettingsList, SettingsPanel, SettingsRow, settingsActionClass } from './SettingsPanel';

export const PROFILE_QUERY_KEY = ['account', 'profile'] as const;

export function ProfileSettingsPanel() {
  const queryClient = useQueryClient();
  const [dialog, setDialog] = useState<'name' | 'address' | null>(null);
  const profileQuery = useQuery({ queryKey: PROFILE_QUERY_KEY, queryFn: getAccountProfile });
  const addressesQuery = useQuery({ queryKey: ADDRESSES_QUERY_KEY, queryFn: listAddresses });

  const profile = profileQuery.data;
  const addresses = addressesQuery.data ?? [];
  const defaultAddress = addresses.find((address) => address.is_default) ?? addresses[0];
  const closeDialog = (open: boolean) => {
    if (!open) setDialog(null);
  };

  return (
    <SettingsPanel title={t('account.profile.settings_title')} description={t('account.profile.settings_subtitle')}>
      <AvatarUploader
        fullName={profile?.full_name ?? ''}
        onUploaded={() => {
          // Keep the cached profile in sync so the form sees the latest URLs.
          void queryClient.invalidateQueries({ queryKey: PROFILE_QUERY_KEY });
        }}
      />

      <h3 className="mt-2 text-qb-h5 font-semibold tracking-normal text-qb-ink">{t('account.profile.personal_info')}</h3>
      {!profile ? (
        <PanelState loading={profileQuery.isLoading} />
      ) : (
        <SettingsList>
          <SettingsRow
            label={t('account.profile.full_name_label')}
            value={profile.full_name}
            action={
              <button type="button" onClick={() => setDialog('name')} className={settingsActionClass}>
                {t('common.edit')}
                <span className="sr-only"> {t('account.profile.full_name_label')}</span>
              </button>
            }
          />
          <SettingsRow
            label={t('account.address.row_label')}
            value={defaultAddress ? addressSummary(defaultAddress) : t('account.address.none')}
            action={
              <button
                type="button"
                onClick={() => setDialog('address')}
                disabled={addressesQuery.isLoading}
                className={settingsActionClass}
              >
                {t('common.edit')}
                <span className="sr-only"> {t('account.address.row_label')}</span>
              </button>
            }
          />
        </SettingsList>
      )}

      {profile ? (
        <>
          <AccountDialog open={dialog === 'name'} onOpenChange={closeDialog} title={t('account.profile.name_dialog_title')}>
            <ProfileForm
              initial={profile}
              fields={['full_name']}
              onSaved={() => setDialog(null)}
              onCancel={() => setDialog(null)}
            />
          </AccountDialog>
          <AddressDialog
            open={dialog === 'address'}
            onOpenChange={closeDialog}
            addresses={addresses}
            defaultName={profile.full_name}
          />
        </>
      ) : null}
    </SettingsPanel>
  );
}
