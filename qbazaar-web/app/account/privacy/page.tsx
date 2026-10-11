'use client';

/**
 * FE-2.6 — Privacy settings: the Data Protection switches of account.html,
 * then a row to the blocked users and the data export.
 *
 * 4 switches backed by `GET / PUT /account/privacy-settings`. Each toggle
 * optimistically flips the cached value, fires the PUT, and rolls back on
 * error. We use TanStack Query's mutation `onMutate / onError / onSuccess`
 * lifecycle so the network failure path is centralised.
 */
import Link from 'next/link';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';

import { showDesignToast } from '@/components/design-system/design-toast';
import { ExportDataRow } from '@/components/account/ExportDataRow';
import { PanelState } from '@/components/account/PanelState';
import { SettingsList, SettingsPanel, SettingsRow, settingsActionClass } from '@/components/account/SettingsPanel';
import { SettingsToggleList } from '@/components/account/SettingsToggleList';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import {
  getPrivacySettings,
  updatePrivacySettings,
} from '@/lib/api/account';
import { ApiClientError } from '@/lib/api/auth';
import type { PrivacySettings } from '@/lib/api/types';

const DEFAULT_SETTINGS: PrivacySettings = {
  show_phone: true,
  show_email: false,
  allow_chat: true,
  indexed_by_search: true,
};

const QUERY_KEY = ['account', 'privacy-settings'] as const;

interface PrivacyField {
  key: keyof PrivacySettings;
  titleKey: string;
  descriptionKey: string;
}

const FIELDS: PrivacyField[] = [
  {
    key: 'show_phone',
    titleKey: 'account.privacy.fields.show_phone_title',
    descriptionKey: 'account.privacy.fields.show_phone_desc',
  },
  {
    key: 'show_email',
    titleKey: 'account.privacy.fields.show_email_title',
    descriptionKey: 'account.privacy.fields.show_email_desc',
  },
  {
    key: 'allow_chat',
    titleKey: 'account.privacy.fields.allow_chat_title',
    descriptionKey: 'account.privacy.fields.allow_chat_desc',
  },
  {
    key: 'indexed_by_search',
    titleKey: 'account.privacy.fields.indexed_by_search_title',
    descriptionKey: 'account.privacy.fields.indexed_by_search_desc',
  },
];

export default function AccountPrivacyPage() {
  const queryClient = useQueryClient();
  const {
    data: settings = DEFAULT_SETTINGS,
    isLoading,
    error,
  } = useQuery({
    queryKey: QUERY_KEY,
    queryFn: getPrivacySettings,
  });

  const mutation = useMutation({
    mutationFn: updatePrivacySettings,
    onMutate: async (next) => {
      await queryClient.cancelQueries({ queryKey: QUERY_KEY });
      const previous = queryClient.getQueryData<PrivacySettings>(QUERY_KEY);
      queryClient.setQueryData(QUERY_KEY, next);
      return { previous };
    },
    onError: (err, _vars, context) => {
      if (context?.previous) {
        queryClient.setQueryData(QUERY_KEY, context.previous);
      }
      if (err instanceof ApiClientError) {
        toast.error(
          translateMaybeKey(`account.errors.${err.code}`) ||
            translateMaybeKey(`auth.errors.${err.code}`) ||
            t('account.privacy.save_failed'),
        );
      } else {
        toast.error(t('account.privacy.save_failed'));
      }
    },
    onSuccess: () => {
      showDesignToast(t('account.privacy.save_success'));
    },
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: QUERY_KEY });
    },
  });

  const handleToggle = (key: string, value: boolean) => {
    mutation.mutate({ ...settings, [key]: value });
  };

  return (
    <SettingsPanel title={t('account.nav.data_protection')} description={t('account.privacy.subtitle')}>
      {isLoading || error ? (
        <PanelState loading={isLoading} />
      ) : (
        <SettingsToggleList
          items={FIELDS.map((field) => ({
            key: field.key,
            title: t(field.titleKey),
            description: t(field.descriptionKey),
            checked: settings[field.key],
          }))}
          onChange={handleToggle}
          disabled={mutation.isPending}
        />
      )}
      <SettingsList>
        <SettingsRow
          label={t('account.nav.blocked_users')}
          value={t('account.privacy.blocked_value')}
          action={
            <Link href="/account/blocked-users" className={settingsActionClass}>
              {t('account.security.sessions_manage')}
            </Link>
          }
        />
        <ExportDataRow />
      </SettingsList>
    </SettingsPanel>
  );
}
