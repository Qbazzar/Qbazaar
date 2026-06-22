'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { toast } from 'sonner';
import { EyeIcon, EyeOffIcon, Loader2Icon } from 'lucide-react';

import { Button, buttonVariants } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import {
  resetPasswordSchema,
  type ResetPasswordInput,
} from '@/lib/validation/auth';
import { ApiClientError, login, resetPassword } from '@/lib/api/auth';
import { useAuthStore } from '@/store/auth';
import { AuthErrorCode } from '@/lib/api/types';
import { FieldError } from './FieldError';
import { PasswordStrengthIndicator } from './PasswordStrengthIndicator';

export function ResetPasswordForm() {
  const router = useRouter();
  const setAuth = useAuthStore((s) => s.setAuth);
  const search = useSearchParams();
  const email = (search.get('email') ?? '').trim();
  const token = (search.get('token') ?? '').trim();
  const linkValid = Boolean(email) && Boolean(token);

  const [showPassword, setShowPassword] = useState(false);

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
      <div className="space-y-4">
        <header className="space-y-2">
          <h2 className="font-display text-2xl tracking-tight">
            {t('auth.reset_password.missing_params_title')}
          </h2>
          <p className="text-muted-foreground text-sm">
            {t('auth.reset_password.missing_params_body')}
          </p>
        </header>
        <div className="flex flex-col gap-2 sm:flex-row">
          <Link
            href="/forgot-password"
            className={cn(
              buttonVariants(),
              'h-11 rounded-full px-6 text-sm font-semibold',
            )}
          >
            {t('auth.reset_password.go_to_forgot')}
          </Link>
          <Link
            href="/login"
            className={cn(
              buttonVariants({ variant: 'outline' }),
              'h-11 rounded-full px-6 text-sm font-semibold',
            )}
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
    <form onSubmit={onSubmit} noValidate className="space-y-4">
      <p className="text-muted-foreground text-sm">
        {t('auth.reset_password.subtitle')}{' '}
        <span className="text-foreground font-medium" dir="ltr">
          {email}
        </span>
      </p>

      {/* email + token are query-driven; we still register them so RHF posts
          the full ResetPasswordRequest shape and validates them via Zod. */}
      <input type="hidden" {...form.register('email')} />
      <input type="hidden" {...form.register('token')} />

      <div className="space-y-1.5">
        <Label htmlFor="password">
          {t('auth.reset_password.password_label')}
        </Label>
        <div className="relative">
          <Input
            id="password"
            type={showPassword ? 'text' : 'password'}
            autoComplete="new-password"
            dir="ltr"
            placeholder={t('auth.reset_password.password_placeholder')}
            aria-invalid={Boolean(errors.password)}
            aria-describedby={errors.password ? 'password-error' : undefined}
            className="h-10 pe-10"
            {...form.register('password')}
          />
          <button
            type="button"
            onClick={() => setShowPassword((v) => !v)}
            className="text-muted-foreground hover:text-foreground absolute end-2 top-1/2 -translate-y-1/2 rounded p-1 transition-colors"
            aria-label={showPassword ? 'Hide password' : 'Show password'}
            tabIndex={-1}
          >
            {showPassword ? (
              <EyeOffIcon className="size-4" />
            ) : (
              <EyeIcon className="size-4" />
            )}
          </button>
        </div>
        <PasswordStrengthIndicator password={passwordValue ?? ''} />
        <FieldError id="password-error" message={errors.password?.message} />
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="password_confirmation">
          {t('auth.reset_password.password_confirmation_label')}
        </Label>
        <Input
          id="password_confirmation"
          type={showPassword ? 'text' : 'password'}
          autoComplete="new-password"
          dir="ltr"
          placeholder={t(
            'auth.reset_password.password_confirmation_placeholder',
          )}
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
          'h-11 w-full rounded-full text-sm font-semibold',
          submitting && 'cursor-progress',
        )}
      >
        {submitting ? (
          <>
            <Loader2Icon className="size-4 animate-spin" aria-hidden="true" />
            {t('auth.reset_password.submitting')}
          </>
        ) : (
          t('auth.reset_password.submit')
        )}
      </Button>

      <p className="text-muted-foreground text-center text-sm">
        <Link href="/login" className="text-coral font-medium hover:underline">
          {t('auth.reset_password.back_to_login')}
        </Link>
      </p>
    </form>
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
