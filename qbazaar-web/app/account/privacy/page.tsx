'use client';

/**
 * FE-2.6 — Privacy settings, laid out as the Data Protection rows of 397:10175.
 *
 * 4 switches backed by `GET / PUT /account/privacy-settings`. Each toggle
 * optimistically flips the cached value, fires the PUT, and rolls back on
 * error. We use TanStack Query's mutation `onMutate / onError / onSuccess`
 * lifecycle so the network failure path is centralised.
 */
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';

import { Switch } from '@/components/design-system/Switch';
import { PanelState } from '@/components/account/PanelState';
import { SettingsList, SettingsPanel, SettingsRow } from '@/components/account/SettingsPanel';
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
      toast.success(t('account.privacy.save_success'));
    },
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: QUERY_KEY });
    },
  });

  const handleToggle = (key: keyof PrivacySettings, value: boolean) => {
    mutation.mutate({ ...settings, [key]: value });
  };

  return (
    <SettingsPanel title={t('account.nav.data_protection')} description={t('account.privacy.subtitle')}>
      {isLoading || error ? (
        <PanelState loading={isLoading} />
      ) : (
        <SettingsList>
          {FIELDS.map((field) => (
            <SettingsRow
              key={field.key}
              value={<span id={`privacy-${field.key}`}>{t(field.titleKey)}</span>}
              description={<span id={`privacy-${field.key}-desc`}>{t(field.descriptionKey)}</span>}
              action={
                <Switch
                  checked={settings[field.key]}
                  onCheckedChange={(value: boolean) => handleToggle(field.key, value)}
                  disabled={mutation.isPending}
                  aria-labelledby={`privacy-${field.key}`}
                  aria-describedby={`privacy-${field.key}-desc`}
                />
              }
            />
          ))}
        </SettingsList>
      )}
    </SettingsPanel>
  );
}
