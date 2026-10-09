'use client';

/**
 * Email Message panel of account.html: one switch per email topic
 * (`GET / PATCH /account/email-preferences`), then the language the emails
 * and notifications are written in. Each switch saves at once and rolls back
 * if the request fails.
 */
import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';

import { AccountDialog } from '@/components/account/AccountDialog';
import { PanelState } from '@/components/account/PanelState';
import { PROFILE_QUERY_KEY } from '@/components/account/ProfileSettingsPanel';
import { ProfileForm } from '@/components/account/ProfileForm';
import { SettingsList, SettingsPanel, SettingsRow, settingsActionClass } from '@/components/account/SettingsPanel';
import { SettingsToggleList } from '@/components/account/SettingsToggleList';
import { apiErrorMessage } from '@/components/account/api-error-message';
import { getAccountProfile, getEmailPreferences, updateEmailPreferences } from '@/lib/api/account';
import type { EmailPreferences } from '@/lib/api/types';
import { t } from '@/lib/i18n/messages';

const QUERY_KEY = ['account', 'email-preferences'] as const;
const TOPICS: readonly (keyof EmailPreferences)[] = [
  'user_messages',
  'offers',
  'listing_updates',
  'saved_search_alerts',
  'newsletters',
];

export default function AccountEmailMessagesPage() {
  const queryClient = useQueryClient();
  const [editingLanguage, setEditingLanguage] = useState(false);
  const preferences = useQuery({ queryKey: QUERY_KEY, queryFn: getEmailPreferences });
  const profile = useQuery({ queryKey: PROFILE_QUERY_KEY, queryFn: getAccountProfile });

  const mutation = useMutation({
    mutationFn: updateEmailPreferences,
    onMutate: async (patch) => {
      await queryClient.cancelQueries({ queryKey: QUERY_KEY });
      const previous = queryClient.getQueryData<EmailPreferences>(QUERY_KEY);
      if (previous) queryClient.setQueryData(QUERY_KEY, { ...previous, ...patch });
      return { previous };
    },
    onError: (err, _patch, context) => {
      if (context?.previous) queryClient.setQueryData(QUERY_KEY, context.previous);
      toast.error(apiErrorMessage(err));
    },
    onSuccess: (saved) => {
      queryClient.setQueryData(QUERY_KEY, saved);
      toast.success(t('account.email_messages.saved'));
    },
  });

  const language = profile.data?.language;

  return (
    <SettingsPanel title={t('account.nav.email_message')} description={t('account.email_messages.subtitle')}>
      {preferences.data ? (
        <SettingsToggleList
          items={TOPICS.map((topic) => ({
            key: topic,
            title: t(`account.email_messages.topics.${topic}.title`),
            description: t(`account.email_messages.topics.${topic}.description`),
            checked: preferences.data[topic],
          }))}
          onChange={(key, checked) => mutation.mutate({ [key]: checked })}
          disabled={mutation.isPending}
        />
      ) : (
        <PanelState loading={preferences.isLoading} />
      )}

      {profile.data ? (
        <>
          <SettingsList>
            <SettingsRow
              label={t('account.email_messages.language_label')}
              value={t(language === 'en' ? 'account.profile.language_en' : 'account.profile.language_ar')}
              action={
                <button type="button" onClick={() => setEditingLanguage(true)} className={settingsActionClass}>
                  {t('common.edit')}
                  <span className="sr-only"> {t('account.email_messages.language_label')}</span>
                </button>
              }
            />
          </SettingsList>
          <AccountDialog
            open={editingLanguage}
            onOpenChange={setEditingLanguage}
            title={t('account.email_messages.language_label')}
          >
            <ProfileForm
              initial={profile.data}
              fields={['language']}
              onSaved={() => setEditingLanguage(false)}
              onCancel={() => setEditingLanguage(false)}
            />
          </AccountDialog>
        </>
      ) : null}
    </SettingsPanel>
  );
}
