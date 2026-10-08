'use client';

/**
 * PasswordChangeForm — `PUT /account/password`.
 *
 * Reuses the same strength scoring + UI as registration so the standard
 * stays consistent. On success: success toast + reset form fields, and the
 * caller closes the "Edit Password" dialog (411:9755).
 */
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Loader2 } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { Field } from '@/components/design-system/Field';
import { fieldErrorText } from '@/components/auth/FieldError';
import { PasswordInput } from '@/components/auth/PasswordInput';
import { PasswordStrengthIndicator } from '@/components/auth/PasswordStrengthIndicator';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import {
  changePasswordSchema,
  type ChangePasswordInput,
} from '@/lib/validation/account';
import { changePassword } from '@/lib/api/account';
import { ApiClientError } from '@/lib/api/auth';
import { AuthErrorCode, UserErrorCode } from '@/lib/api/types';
import { ModalActions } from './ModalActions';

export interface PasswordChangeFormProps {
  /** Called after a successful change, e.g. to close the dialog. */
  onDone?: () => void;
  onCancel?: () => void;
}

export function PasswordChangeForm({ onDone, onCancel }: PasswordChangeFormProps) {
  const form = useForm<ChangePasswordInput>({
    resolver: zodResolver(changePasswordSchema),
    mode: 'onBlur',
    defaultValues: {
      current_password: '',
      new_password: '',
      password_confirmation: '',
    },
  });

  const mutation = useMutation({
    mutationFn: changePassword,
    onSuccess: () => {
      toast.success(t('account.security.success'));
      form.reset({
        current_password: '',
        new_password: '',
        password_confirmation: '',
      });
      onDone?.();
    },
  });

  const onSubmit = form.handleSubmit(async (values) => {
    try {
      await mutation.mutateAsync(values);
    } catch (err) {
      handleSubmitError(err, form);
    }
  });

  const errors = form.formState.errors;
  const submitting = form.formState.isSubmitting || mutation.isPending;
  const newPasswordValue = form.watch('new_password');

  return (
    <form onSubmit={onSubmit} noValidate className="flex flex-col gap-6 text-start">
      <p className="rounded-qb-md bg-qb-brand-soft px-4 py-3 text-qb-caption text-qb-ink-body">
        {t('account.security.sign_out_notice')}
      </p>

      <Field
        label={t('account.security.current_password_label')}
        required
        error={fieldErrorText(errors.current_password?.message)}
      >
        {(control) => (
          <PasswordInput
            {...control}
            autoComplete="current-password"
            placeholder="••••••••"
            {...form.register('current_password')}
          />
        )}
      </Field>

      <Field label={t('account.security.new_password_label')} required error={fieldErrorText(errors.new_password?.message)}>
        {(control) => (
          <div className="flex flex-col gap-3">
            <PasswordInput
              {...control}
              autoComplete="new-password"
              placeholder="••••••••"
              {...form.register('new_password')}
            />
            <PasswordStrengthIndicator password={newPasswordValue ?? ''} />
          </div>
        )}
      </Field>

      <Field
        label={t('account.security.password_confirmation_label')}
        required
        error={fieldErrorText(errors.password_confirmation?.message)}
      >
        {(control) => (
          <PasswordInput
            {...control}
            autoComplete="new-password"
            placeholder="••••••••"
            {...form.register('password_confirmation')}
          />
        )}
      </Field>

      <ModalActions>
        <Button type="submit" size="sm" disabled={submitting} className={cn(submitting && 'cursor-progress')}>
          {submitting ? (
            <>
              <Loader2 className="animate-spin" aria-hidden="true" />
              {t('account.security.submitting')}
            </>
          ) : (
            t('common.save')
          )}
        </Button>
        {onCancel ? (
          <Button type="button" variant="muted" size="sm" onClick={onCancel} disabled={submitting}>
            {t('common.cancel')}
          </Button>
        ) : null}
      </ModalActions>
    </form>
  );
}

function handleSubmitError(
  err: unknown,
  form: ReturnType<typeof useForm<ChangePasswordInput>>,
) {
  if (err instanceof ApiClientError) {
    if (err.code === AuthErrorCode.ValidationFailed && err.details) {
      // The API validates the new password under the key `password` (Laravel's
      // `confirmed` rule), while the form field is `new_password` — map it so
      // the "password too weak / same as current" message actually shows.
      const fieldMap: Record<string, keyof ChangePasswordInput> = {
        current_password: 'current_password',
        password: 'new_password',
        new_password: 'new_password',
        password_confirmation: 'password_confirmation',
      };
      let mapped = false;
      for (const [field, messages] of Object.entries(err.details)) {
        const formField = fieldMap[field];
        if (formField && messages?.length) {
          form.setError(formField, {
            type: 'server',
            message: messages[0],
          });
          mapped = true;
        }
      }
      if (mapped) return;
    }
    if (err.code === UserErrorCode.PasswordIncorrect) {
      const msg = t('account.errors.USER_002');
      form.setError('current_password', { type: 'server', message: msg });
      toast.error(msg);
      return;
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
