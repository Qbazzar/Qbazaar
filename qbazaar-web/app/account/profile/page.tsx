'use client';

/**
 * FE-2.2 — Profile settings (393:8828).
 *
 * Avatar + "Change Profile photo", then the personal information rows. Each
 * "Edit" opens the profile form in a dialog (401:11108); the form owns the
 * RHF + Zod validation and the `PUT /account/profile` mutation.
 */
import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';

import { Modal } from '@/components/design-system/Modal';
import { AvatarUploader } from '@/components/account/AvatarUploader';
import { PanelState } from '@/components/account/PanelState';
import { ProfileForm, type ProfileField } from '@/components/account/ProfileForm';
import { SettingsList, SettingsPanel, SettingsRow, settingsActionClass } from '@/components/account/SettingsPanel';
import { t } from '@/lib/i18n/messages';
import { getAccountProfile } from '@/lib/api/account';

const ROWS: readonly { field: ProfileField; labelKey: string }[] = [
  { field: 'full_name', labelKey: 'account.profile.full_name_label' },
  { field: 'language', labelKey: 'account.profile.language_label' },
  { field: 'bio', labelKey: 'account.profile.bio_label' },
];

export default function AccountProfilePage() {
  const queryClient = useQueryClient();
  const [editing, setEditing] = useState<ProfileField | null>(null);
  const {
    data: profile,
    isLoading,
    error,
  } = useQuery({
    queryKey: ['account', 'profile'],
    queryFn: getAccountProfile,
  });

  const valueOf = (field: ProfileField): string => {
    if (!profile) return '';
    if (field === 'language') {
      return t(profile.language === 'en' ? 'account.profile.language_en' : 'account.profile.language_ar');
    }
    if (field === 'bio') return profile.bio?.trim() || t('account.profile.bio_empty');
    return profile.full_name;
  };

  return (
    <SettingsPanel title={t('account.profile.settings_title')} description={t('account.profile.settings_subtitle')}>
      <AvatarUploader
        fullName={profile?.full_name ?? ''}
        onUploaded={() => {
          // Keep the cached profile in sync so the form sees the latest URLs.
          queryClient.invalidateQueries({ queryKey: ['account', 'profile'] });
        }}
      />

      <h2 className="mt-10 text-qb-body-lg font-semibold tracking-normal text-qb-ink qb-tablet:text-qb-h5">
        {t('account.profile.personal_info')}
      </h2>
      {isLoading || error || !profile ? (
        <PanelState loading={isLoading} />
      ) : (
        <SettingsList className="mt-6 qb-desktop:mt-[29px]">
          {ROWS.map(({ field, labelKey }) => (
            <SettingsRow
              key={field}
              label={t(labelKey)}
              value={valueOf(field)}
              action={
                <button type="button" onClick={() => setEditing(field)} className={settingsActionClass}>
                  {t('common.edit')}
                  <span className="sr-only"> {t(labelKey)}</span>
                </button>
              }
            />
          ))}
        </SettingsList>
      )}

      {profile ? (
        <Modal
          open={editing !== null}
          onOpenChange={(open) => {
            if (!open) setEditing(null);
          }}
          title={t('account.profile.edit_title')}
          className="max-w-[538px]"
        >
          <ProfileForm
            initial={profile}
            focusField={editing ?? undefined}
            onSaved={() => setEditing(null)}
            onCancel={() => setEditing(null)}
          />
        </Modal>
      ) : null}
    </SettingsPanel>
  );
}
