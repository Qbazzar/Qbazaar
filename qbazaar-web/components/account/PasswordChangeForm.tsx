'use client';

/**
 * PasswordChangeForm — `PUT /account/password`.
 *
 * Reuses the same strength scoring + UI as registration so the standard
 * stays consistent. On success: success toast + reset form fields (we don't
 * navigate away — the user might want to tweak other security settings).
 */
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation } from '@tanstack/react-query';
import { toast } from 'sonner';
import { EyeIcon, EyeOffIcon, Loader2Icon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { FieldError } from '@/components/auth/FieldError';
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

export function PasswordChangeForm() {
  const [showCurrent, setShowCurrent] = useState(false);
  const [showNew, setShowNew] = useState(false);

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
    <form onSubmit={onSubmit} noValidate className="space-y-5">
      <p className="border-coral/30 bg-coral/5 text-ink-700 rounded-xl border px-4 py-3 text-sm">
        {t('account.security.sign_out_notice')}
      </p>

      <PasswordField
        id="current_password"
        label={t('account.security.current_password_label')}
        autoComplete="current-password"
        show={showCurrent}
        onToggle={() => setShowCurrent((v) => !v)}
        error={errors.current_password?.message}
        register={form.register('current_password')}
      />

      <div className="space-y-1.5">
        <Label htmlFor="new_password">
          {t('account.security.new_password_label')}
        </Label>
        <div className="relative" dir="ltr">
          <Input
            id="new_password"
            type={showNew ? 'text' : 'password'}
            autoComplete="new-password"
            placeholder="••••••••"
            aria-invalid={Boolean(errors.new_password)}
            aria-describedby={
              errors.new_password ? 'new_password-error' : undefined
            }
            className="h-10 pr-10"
            {...form.register('new_password')}
          />
          <button
            type="button"
            onClick={() => setShowNew((v) => !v)}
            className="text-muted-foreground hover:text-foreground absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 transition-colors"
            aria-label={showNew ? 'Hide password' : 'Show password'}
            tabIndex={-1}
          >
            {showNew ? (
              <EyeOffIcon className="size-4" />
            ) : (
              <EyeIcon className="size-4" />
            )}
          </button>
        </div>
        <PasswordStrengthIndicator password={newPasswordValue ?? ''} />
        <FieldError
          id="new_password-error"
          message={errors.new_password?.message}
        />
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="password_confirmation">
          {t('account.security.password_confirmation_label')}
        </Label>
        <Input
          id="password_confirmation"
          type={showNew ? 'text' : 'password'}
          autoComplete="new-password"
          dir="ltr"
          placeholder="••••••••"
          aria-invalid={Boolean(errors.password_confirmation)}
          aria-describedby={
            errors.password_confirmation
              ? 'password_confirmation-error'
              : undefined
          }
          className="h-10"
          {...form.register('password_confirmation')}
        />
        <FieldError
          id="password_confirmation-error"
          message={errors.password_confirmation?.message}
        />
      </div>

      <Button
        type="submit"
        size="lg"
        disabled={submitting}
        className={cn(
          'h-11 rounded-full px-6 text-sm font-semibold',
          submitting && 'cursor-progress',
        )}
      >
        {submitting ? (
          <>
            <Loader2Icon className="size-4 animate-spin" aria-hidden />
            {t('account.security.submitting')}
          </>
        ) : (
          t('account.security.submit')
        )}
      </Button>
    </form>
  );
}

function PasswordField({
  id,
  label,
  autoComplete,
  show,
  onToggle,
  error,
  register,
}: {
  id: string;
  label: string;
  autoComplete: string;
  show: boolean;
  onToggle: () => void;
  error?: string;
  register: ReturnType<ReturnType<typeof useForm<ChangePasswordInput>>['register']>;
}) {
  return (
    <div className="space-y-1.5">
      <Label htmlFor={id}>{label}</Label>
      <div className="relative" dir="ltr">
        <Input
          id={id}
          type={show ? 'text' : 'password'}
          autoComplete={autoComplete}
          placeholder="••••••••"
          aria-invalid={Boolean(error)}
          aria-describedby={error ? `${id}-error` : undefined}
          className="h-10 pr-10"
          {...register}
        />
        <button
          type="button"
          onClick={onToggle}
          className="text-muted-foreground hover:text-foreground absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 transition-colors"
          aria-label={show ? 'Hide password' : 'Show password'}
          tabIndex={-1}
        >
          {show ? (
            <EyeOffIcon className="size-4" />
          ) : (
            <EyeIcon className="size-4" />
          )}
        </button>
      </div>
      <FieldError id={`${id}-error`} message={error} />
    </div>
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
