'use client';

import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { toast } from 'sonner';
import { Loader2 } from 'lucide-react';

import { Button, buttonVariants } from '@/components/design-system/Button';
import { Field } from '@/components/design-system/Field';
import { cn } from '@/lib/utils';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import {
  resetPasswordSchema,
  type ResetPasswordInput,
} from '@/lib/validation/auth';
import { ApiClientError, login, resetPassword } from '@/lib/api/auth';
import { useAuthStore } from '@/store/auth';
import { AuthErrorCode } from '@/lib/api/types';
import { authButtonClass, authSubmitClass } from './AuthFooter';
import { AuthHeading } from './AuthHeading';
import { fieldErrorText } from './FieldError';
import { PasswordInput } from './PasswordInput';

export function ResetPasswordForm() {
  const router = useRouter();
  const setAuth = useAuthStore((s) => s.setAuth);
  const search = useSearchParams();
  const email = (search.get('email') ?? '').trim();
  const token = (search.get('token') ?? '').trim();
  const linkValid = Boolean(email) && Boolean(token);

  const form = useForm<ResetPasswordInput>({
    resolver: zodResolver(resetPasswordSchema),
    // The reset link carries email/token; the user only types the password
    // pair. We seed the hidden fields so RHF carries them into the payload.
    defaultValues: {
      email,
      token,
      password: '',
      password_confirmation: '',
    },
    mode: 'onBlur',
  });

  const onSubmit = form.handleSubmit(async (values) => {
    try {
      await resetPassword(values);
      // Auto-login with the brand-new password so the user lands signed-in
      // instead of on the login page, where browser autofill would re-submit
      // their OLD saved password (the #1 "I reset but can't log in" cause).
      try {
        const data = await login({
          identifier: values.email,
          password: values.password,
        });
        setAuth({
          user: data.user,
          accessToken: data.tokens.access_token,
        });
        toast.success(t('auth.reset_password.success_toast'));
        router.replace('/account');
      } catch {
        // Reset succeeded but auto-login didn't — fall back to manual login.
        toast.success(t('auth.reset_password.success_toast'));
        router.replace('/login');
      }
    } catch (err) {
      handleSubmitError(err, form);
    }
  });

  if (!linkValid) {
    return (
      <div className="text-center">
        <AuthHeading
          title={t('auth.reset_password.missing_params_title')}
          subtitle={t('auth.reset_password.missing_params_body')}
        />
        <div className="mx-auto mt-[34px] flex w-full max-w-[420px] flex-col gap-3">
          <Link href="/forgot-password" className={cn(buttonVariants({ fullWidth: true }), authSubmitClass)}>
            {t('auth.reset_password.go_to_forgot')}
          </Link>
          <Link
            href="/login"
            className={cn(buttonVariants({ variant: 'outline', fullWidth: true }), authButtonClass)}
          >
            {t('auth.reset_password.back_to_login')}
          </Link>
        </div>
      </div>
    );
  }

  const errors = form.formState.errors;
  const submitting = form.formState.isSubmitting;

  return (
    <>
      <AuthHeading
        title={t('auth.reset_password.title')}
        subtitle={
          <>
            {t('auth.reset_password.subtitle')}{' '}
            <span dir="ltr">{email}</span>
          </>
        }
      />
      <form method="post" onSubmit={onSubmit} noValidate className="flex flex-col">
        {/* email + token are query-driven; we still register them so RHF posts
            the full ResetPasswordRequest shape and validates them via Zod. */}
        <input type="hidden" {...form.register('email')} />
        <input type="hidden" {...form.register('token')} />

        <Field
          label={t('auth.reset_password.password_label')}
          required
          error={fieldErrorText(errors.password?.message)}
          className="mt-[22px]"
        >
          {(control) => (
            <PasswordInput
              {...control}
              autoComplete="new-password"
              placeholder={t('auth.reset_password.password_placeholder')}
              {...form.register('password')}
            />
          )}
        </Field>

        <Field
          label={t('auth.reset_password.password_confirmation_label')}
          required
          error={fieldErrorText(errors.password_confirmation?.message)}
          className="mt-[18px]"
        >
          {(control) => (
            <PasswordInput
              {...control}
              autoComplete="new-password"
              placeholder={t('auth.reset_password.password_confirmation_placeholder')}
              {...form.register('password_confirmation')}
            />
          )}
        </Field>

        <Button
          type="submit"
          fullWidth
          disabled={submitting}
          className={cn(authSubmitClass, 'mt-[26px]', submitting && 'cursor-progress')}
        >
          {submitting ? (
            <>
              <Loader2 className="animate-spin" aria-hidden="true" />
              {t('auth.reset_password.submitting')}
            </>
          ) : (
            t('auth.reset_password.submit')
          )}
        </Button>
      </form>
    </>
  );
}

function handleSubmitError(
  err: unknown,
  form: ReturnType<typeof useForm<ResetPasswordInput>>,
) {
  if (err instanceof ApiClientError) {
    if (err.code === AuthErrorCode.ValidationFailed && err.details) {
      const known: (keyof ResetPasswordInput)[] = [
        'email',
        'token',
        'password',
        'password_confirmation',
      ];
      let mapped = false;
      for (const [field, messages] of Object.entries(err.details)) {
        if ((known as string[]).includes(field) && messages?.length) {
          form.setError(field as keyof ResetPasswordInput, {
            type: 'server',
            message: messages[0],
          });
          mapped = true;
        }
      }
      if (mapped) return;
    }
    const fallback =
      translateMaybeKey(`auth.errors.${err.code}`) || err.message;
    toast.error(fallback);
    return;
  }
  toast.error(t('auth.errors.unknown'));
}
