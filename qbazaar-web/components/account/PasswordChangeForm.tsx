'use client';

/**
 * PasswordChangeForm — `PUT /account/password`.
 *
 * The "Change Password" dialog of account.html: current, new and confirm.
 * On success: success toast + reset form fields, and the caller closes the
 * dialog. The API signs the other devices out, which the toast says.
 */
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Loader2 } from 'lucide-react';

import { showDesignToast } from '@/components/design-system/design-toast';
import { Field } from '@/components/design-system/Field';
import { Input } from '@/components/design-system/Input';
import { fieldErrorText } from '@/components/auth/FieldError';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import {
  changePasswordSchema,
  type ChangePasswordInput,
} from '@/lib/validation/account';
import { changePassword } from '@/lib/api/account';
import { ApiClientError } from '@/lib/api/auth';
import { AuthErrorCode, UserErrorCode } from '@/lib/api/types';
import { AccountDialogActions, accountCancelClass, accountInputClass, accountSaveClass } from './AccountDialog';

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
      showDesignToast(t('account.security.success'));
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

  const passwordField = (name: keyof ChangePasswordInput, labelKey: string, autoComplete: string) => (
    <Field label={t(labelKey)} error={fieldErrorText(errors[name]?.message)}>
      {(control) => (
        <Input
          {...control}
          type="password"
          dir="ltr"
          autoComplete={autoComplete}
          placeholder="*******"
          className={cn(accountInputClass, 'rtl:placeholder:text-right')}
          {...form.register(name)}
        />
      )}
    </Field>
  );

  return (
    <form onSubmit={onSubmit} noValidate className="flex flex-col gap-[18px] text-start">
      {passwordField('current_password', 'account.security.current_password_label', 'current-password')}
      {passwordField('new_password', 'account.security.new_password_label', 'new-password')}
      {passwordField('password_confirmation', 'account.security.password_confirmation_label', 'new-password')}

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
