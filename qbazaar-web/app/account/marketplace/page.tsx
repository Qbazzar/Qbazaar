'use client';

/**
 * Marketplace Info panel of account.html: what other members see of the
 * seller. The seller type, the bio, and for business accounts the store name
 * of the business profile (`GET / PUT /account/business-profile`).
 */
import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Loader2 } from 'lucide-react';

import {
  AccountDialog,
  AccountDialogActions,
  accountCancelClass,
  accountInputClass,
  accountSaveClass,
} from '@/components/account/AccountDialog';
import { PanelState } from '@/components/account/PanelState';
import { PROFILE_QUERY_KEY } from '@/components/account/ProfileSettingsPanel';
import { ProfileForm } from '@/components/account/ProfileForm';
import { SettingsList, SettingsPanel, SettingsRow, settingsActionClass } from '@/components/account/SettingsPanel';
import { apiErrorMessage } from '@/components/account/api-error-message';
import { Field } from '@/components/design-system/Field';
import { Input } from '@/components/design-system/Input';
import { useAuth } from '@/hooks/useAuth';
import { getAccountProfile, getBusinessProfile, updateBusinessProfile } from '@/lib/api/account';
import type { BusinessProfile } from '@/lib/api/types';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

const BUSINESS_QUERY_KEY = ['account', 'business-profile'] as const;
const STORE_NAME_MAX = 120;

type Dialog = 'bio' | 'store';

export default function AccountMarketplacePage() {
  const { user } = useAuth();
  const [dialog, setDialog] = useState<Dialog | null>(null);
  const isBusiness = user?.account_type === 'business';
  const profile = useQuery({ queryKey: PROFILE_QUERY_KEY, queryFn: getAccountProfile });
  const business = useQuery({ queryKey: BUSINESS_QUERY_KEY, queryFn: getBusinessProfile, enabled: isBusiness });

  if (!user) return null;

  const editButton = (target: Dialog, labelKey: string) => (
    <button type="button" onClick={() => setDialog(target)} className={settingsActionClass}>
      {t('common.edit')}
      <span className="sr-only"> {t(labelKey)}</span>
    </button>
  );
  const closeDialog = (open: boolean) => {
    if (!open) setDialog(null);
  };

  return (
    <SettingsPanel title={t('account.nav.marketplace_info')} description={t('account.marketplace.subtitle')}>
      {profile.data ? (
        <SettingsList>
          <SettingsRow
            label={t('account.marketplace.seller_type')}
            value={t(isBusiness ? 'account.profile.business_seller' : 'account.profile.private_seller')}
          />
          {isBusiness ? (
            <SettingsRow
              label={t('account.marketplace.store_name')}
              value={business.data?.business_name || t('account.marketplace.store_name_empty')}
              action={business.data ? editButton('store', 'account.marketplace.store_name') : null}
            />
          ) : null}
          <SettingsRow
            label={t('account.profile.bio_label')}
            value={<span className="break-words">{profile.data.bio?.trim() || t('account.profile.bio_empty')}</span>}
            action={editButton('bio', 'account.profile.bio_label')}
          />
        </SettingsList>
      ) : (
        <PanelState loading={profile.isLoading} />
      )}

      {profile.data ? (
        <AccountDialog open={dialog === 'bio'} onOpenChange={closeDialog} title={t('account.profile.bio_label')}>
          <ProfileForm
            initial={profile.data}
            fields={['bio']}
            onSaved={() => setDialog(null)}
            onCancel={() => setDialog(null)}
          />
        </AccountDialog>
      ) : null}
      {business.data ? (
        <AccountDialog open={dialog === 'store'} onOpenChange={closeDialog} title={t('account.marketplace.store_name')}>
          <StoreNameForm business={business.data} onDone={() => setDialog(null)} />
        </AccountDialog>
      ) : null}
    </SettingsPanel>
  );
}

function StoreNameForm({ business, onDone }: { business: BusinessProfile; onDone: () => void }) {
  const queryClient = useQueryClient();
  const [name, setName] = useState(business.business_name ?? '');
  const [error, setError] = useState<string | null>(null);
  const save = useMutation({
    mutationFn: (businessName: string | null) => updateBusinessProfile({ business_name: businessName }),
    onSuccess: (saved) => {
      queryClient.setQueryData(BUSINESS_QUERY_KEY, saved);
      toast.success(t('account.profile.success'));
      onDone();
    },
    onError: (err) => setError(apiErrorMessage(err)),
  });

  return (
    <form
      noValidate
      onSubmit={(event) => {
        event.preventDefault();
        save.mutate(name.trim() || null);
      }}
      className="text-start"
    >
      <Field label={t('account.marketplace.store_name')} error={error ?? undefined}>
        {(control) => (
          <Input
            {...control}
            value={name}
            maxLength={STORE_NAME_MAX}
            onChange={(event) => {
              setError(null);
              setName(event.target.value);
            }}
            className={cn(accountInputClass, 'py-[15px] text-qb-body')}
          />
        )}
      </Field>
      <AccountDialogActions>
        <button type="submit" disabled={save.isPending} className={accountSaveClass}>
          {save.isPending ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
          {t('common.save')}
        </button>
        <button type="button" onClick={onDone} disabled={save.isPending} className={accountCancelClass}>
          {t('common.cancel')}
        </button>
      </AccountDialogActions>
    </form>
  );
}
