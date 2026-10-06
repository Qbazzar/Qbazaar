'use client';

import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { toast } from 'sonner';
import { KeyRound, Loader2 } from 'lucide-react';

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
import { AuthFooter, authLinkClass, authSubmitClass } from './AuthFooter';
import { AuthHeading } from './AuthHeading';
import { announcedError } from './FieldError';
import { PasswordInput } from './PasswordInput';
import { PasswordStrengthIndicator } from './PasswordStrengthIndicator';

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
      <div className="flex flex-col items-center gap-8">
        <AuthHeading
          icon={<KeyRound />}
          title={t('auth.reset_password.missing_params_title')}
          subtitle={t('auth.reset_password.missing_params_body')}
        />
        <div className="flex w-full max-w-[420px] flex-col gap-3">
          <Link href="/forgot-password" className={cn(buttonVariants({ fullWidth: true }), authSubmitClass)}>
            {t('auth.reset_password.go_to_forgot')}
          </Link>
          <Link
            href="/login"
            className={cn(buttonVariants({ variant: 'outline', fullWidth: true }), authSubmitClass)}
          >
            {t('auth.reset_password.back_to_login')}
          </Link>
        </div>
      </div>
    );
  }

  const errors = form.formState.errors;
  const submitting = form.formState.isSubmitting;
  const passwordValue = form.watch('password');

  return (
    <>
      <AuthHeading
        title={t('auth.reset_password.title')}
        subtitle={
          <>
            {t('auth.reset_password.subtitle')}{' '}
            <span className="font-semibold text-qb-ink-body" dir="ltr">
              {email}
            </span>
          </>
        }
      />
      <form onSubmit={onSubmit} noValidate className="mt-8 flex flex-col gap-6">
        {/* email + token are query-driven; we still register them so RHF posts
            the full ResetPasswordRequest shape and validates them via Zod. */}
        <input type="hidden" {...form.register('email')} />
        <input type="hidden" {...form.register('token')} />

        <Field label={t('auth.reset_password.password_label')} required error={announcedError(errors.password?.message)}>
          {(control) => (
            <div className="flex flex-col gap-3">
              <PasswordInput
                {...control}
                autoComplete="new-password"
                placeholder={t('auth.reset_password.password_placeholder')}
                {...form.register('password')}
              />
              <PasswordStrengthIndicator password={passwordValue ?? ''} />
            </div>
          )}
        </Field>

        <Field
          label={t('auth.reset_password.password_confirmation_label')}
          required
          error={announcedError(errors.password_confirmation?.message)}
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

        <Button type="submit" fullWidth disabled={submitting} className={cn(authSubmitClass, submitting && 'cursor-progress')}>
          {submitting ? (
            <>
              <Loader2 className="animate-spin" aria-hidden="true" />
              {t('auth.reset_password.submitting')}
            </>
          ) : (
            t('auth.reset_password.submit')
          )}
        </Button>

        <AuthFooter>
          <Link href="/login" className={authLinkClass}>
            {t('auth.reset_password.back_to_login')}
          </Link>
        </AuthFooter>
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
