'use client';

/**
 * ProfileForm — edits the authenticated user's profile.
 *
 * Initial values come from `GET /account/profile`. Submission calls
 * `PUT /account/profile`, then syncs the in-memory auth store + invalidates
 * the React Query cache so other parts of the UI (header, dashboard) pick
 * up the new name immediately.
 */
import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Loader2 } from 'lucide-react';

import { showDesignToast } from '@/components/design-system/design-toast';
import { Field } from '@/components/design-system/Field';
import { Input, Select, Textarea } from '@/components/design-system/Input';
import { fieldErrorText } from '@/components/auth/FieldError';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import {
  profileSchema,
  type ProfileInput,
} from '@/lib/validation/account';
import { updateAccountProfile } from '@/lib/api/account';
import { ApiClientError } from '@/lib/api/auth';
import { AuthErrorCode } from '@/lib/api/types';
import type { AccountProfile, Language } from '@/lib/api/types';
import { useAuthStore } from '@/store/auth';
import { AccountDialogActions, accountCancelClass, accountInputClass, accountSaveClass } from './AccountDialog';

export type ProfileField = keyof ProfileInput;

export interface ProfileFormProps {
  initial: AccountProfile;
  /** The fields this dialog edits; the others are sent back unchanged. */
  fields: readonly ProfileField[];
  onSaved?: () => void;
  onCancel?: () => void;
}

export function ProfileForm({ initial, fields, onSaved, onCancel }: ProfileFormProps) {
  const queryClient = useQueryClient();
  const setUser = useAuthStore((s) => s.setUser);
  const currentUser = useAuthStore((s) => s.user);

  const form = useForm<ProfileInput>({
    resolver: zodResolver(profileSchema),
    mode: 'onBlur',
    defaultValues: {
      full_name: initial.full_name,
      language: (initial.language ?? 'ar') as Language,
      bio: initial.bio ?? '',
    },
  });

  // Re-seed if the upstream query refreshes after the form has mounted.
  useEffect(() => {
    form.reset({
      full_name: initial.full_name,
      language: (initial.language ?? 'ar') as Language,
      bio: initial.bio ?? '',
    });
  }, [initial.full_name, initial.language, initial.bio, form]);

  const [firstField] = fields;
  useEffect(() => {
    if (firstField) form.setFocus(firstField);
  }, [firstField, form]);

  const mutation = useMutation({
    mutationFn: updateAccountProfile,
    onSuccess: (updated) => {
      // Keep auth store in sync so the header and the hub refresh.
      if (currentUser) {
        setUser({
          ...currentUser,
          full_name: updated.full_name,
          language: updated.language,
        });
      }
      queryClient.setQueryData(['account', 'profile'], updated);
      showDesignToast(t('account.profile.success'));
      onSaved?.();
    },
  });

  const onSubmit = form.handleSubmit(async (values) => {
    try {
      await mutation.mutateAsync({
        full_name: values.full_name,
        language: values.language,
        bio: values.bio ?? null,
      });
    } catch (err) {
      handleSubmitError(err, form);
    }
  });

  const errors = form.formState.errors;
  const submitting = form.formState.isSubmitting || mutation.isPending;

  return (
    <form onSubmit={onSubmit} noValidate className="flex flex-col gap-[18px] text-start">
      {fields.includes('full_name') ? (
        <Field label={t('account.profile.full_name_label')} error={fieldErrorText(errors.full_name?.message)}>
          {(control) => (
            <Input
              {...control}
              type="text"
              autoComplete="name"
              className={cn(accountInputClass, 'py-[15px] text-qb-body')}
              placeholder={t('account.profile.full_name_placeholder')}
              {...form.register('full_name')}
            />
          )}
        </Field>
      ) : null}

      {fields.includes('language') ? (
        <Field label={t('account.profile.language_label')} error={fieldErrorText(errors.language?.message)}>
          {(control) => (
            <Select {...control} className={cn(accountInputClass, 'pe-11')} {...form.register('language')}>
              <option value="ar">{t('account.profile.language_ar')}</option>
              <option value="en">{t('account.profile.language_en')}</option>
            </Select>
          )}
        </Field>
      ) : null}

      {fields.includes('bio') ? (
        <Field
          label={t('account.profile.bio_label')}
          hint={t('account.profile.bio_hint')}
          error={fieldErrorText(errors.bio?.message)}
        >
          {(control) => (
            <Textarea
              {...control}
              rows={4}
              maxLength={280}
              className={accountInputClass}
              placeholder={t('account.profile.bio_placeholder')}
              {...form.register('bio')}
            />
          )}
        </Field>
      ) : null}

      <AccountDialogActions className="mt-2">
        <button type="submit" disabled={submitting} className={cn(accountSaveClass, submitting && 'cursor-progress')}>
          {submitting ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
          {t('common.save')}
        </button>
        {onCancel ? (
          <button type="button" onClick={onCancel} disabled={submitting} className={accountCancelClass}>
            {t('common.cancel')}
          </button>
        ) : null}
      </AccountDialogActions>
    </form>
  );
}

function handleSubmitError(
  err: unknown,
  form: ReturnType<typeof useForm<ProfileInput>>,
) {
  if (err instanceof ApiClientError) {
    if (err.code === AuthErrorCode.ValidationFailed && err.details) {
      const known: (keyof ProfileInput)[] = ['full_name', 'language', 'bio'];
      let mapped = false;
      for (const [field, messages] of Object.entries(err.details)) {
        if ((known as string[]).includes(field) && messages?.length) {
          form.setError(field as keyof ProfileInput, {
            type: 'server',
            message: messages[0],
          });
          mapped = true;
        }
      }
      if (mapped) return;
    }
    const fallback =
      translateMaybeKey(`account.errors.${err.code}`) ||
      translateMaybeKey(`auth.errors.${err.code}`) ||
      err.message;
    toast.error(fallback);
    return;
  }
  toast.error(t('auth.errors.unknown'));
}
