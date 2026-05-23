'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { toast } from 'sonner';
import { EyeIcon, EyeOffIcon, Loader2Icon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import { loginSchema, type LoginInput } from '@/lib/validation/auth';
import { ApiClientError, login } from '@/lib/api/auth';
import { useAuthStore } from '@/store/auth';
import { AuthErrorCode } from '@/lib/api/types';
import { FieldError } from './FieldError';

const SAFE_REDIRECT = /^\/(?!\/)[^\s]*$/;

export function LoginForm() {
  const router = useRouter();
  const search = useSearchParams();
  const setAuth = useAuthStore((s) => s.setAuth);
  const [showPassword, setShowPassword] = useState(false);

  const form = useForm<LoginInput>({
    resolver: zodResolver(loginSchema),
    defaultValues: { identifier: '', password: '' },
    mode: 'onBlur',
  });

  const onSubmit = form.handleSubmit(async (values) => {
    try {
      const data = await login(values);
      setAuth({ user: data.user, accessToken: data.tokens.access_token });
      toast.success(t('auth.login.success'));

      const from = search.get('from');
      const target = from && SAFE_REDIRECT.test(from) ? from : '/';
      router.replace(target);
    } catch (err) {
      handleSubmitError(err, form);
    }
  });

  const idError = form.formState.errors.identifier?.message;
  const passwordError = form.formState.errors.password?.message;
  const submitting = form.formState.isSubmitting;

  return (
    <form onSubmit={onSubmit} noValidate className="space-y-4">
      <div className="space-y-1.5">
        <Label htmlFor="identifier">{t('auth.login.identifier_label')}</Label>
        <Input
          id="identifier"
          type="text"
          autoComplete="username"
          dir="ltr"
          placeholder={t('auth.login.identifier_placeholder')}
          aria-invalid={Boolean(idError)}
          aria-describedby={idError ? 'identifier-error' : undefined}
          className="h-10"
          {...form.register('identifier')}
        />
        <FieldError id="identifier-error" message={idError} />
      </div>

      <div className="space-y-1.5">
        <div className="flex items-center justify-between">
          <Label htmlFor="password">{t('auth.login.password_label')}</Label>
          <Link
            href="/forgot-password"
            className="text-coral text-xs font-medium hover:underline"
          >
            {t('auth.login.forgot')}
          </Link>
        </div>
        <div className="relative">
          <Input
            id="password"
            type={showPassword ? 'text' : 'password'}
            autoComplete="current-password"
            dir="ltr"
            placeholder={t('auth.login.password_placeholder')}
            aria-invalid={Boolean(passwordError)}
            aria-describedby={passwordError ? 'password-error' : undefined}
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
        <FieldError id="password-error" message={passwordError} />
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
            {t('auth.login.submitting')}
          </>
        ) : (
          t('auth.login.submit')
        )}
      </Button>

      <p className="text-muted-foreground text-center text-sm">
        {t('auth.login.no_account')}{' '}
        <Link href="/register" className="text-coral font-medium hover:underline">
          {t('auth.login.go_to_register')}
        </Link>
      </p>
    </form>
  );
}

// ── Error mapping ───────────────────────────────────────────────────────────
function handleSubmitError(
  err: unknown,
  form: ReturnType<typeof useForm<LoginInput>>,
) {
  if (err instanceof ApiClientError) {
    if (err.code === AuthErrorCode.ValidationFailed && err.details) {
      // Map server-side validation errors to the matching fields.
      for (const [field, messages] of Object.entries(err.details)) {
        if (field === 'identifier' || field === 'password') {
          form.setError(field, {
            type: 'server',
            message: messages[0],
          });
        }
      }
      return;
    }
    if (err.code === AuthErrorCode.InvalidCredentials) {
      const msg = t('auth.errors.AUTH_001');
      form.setError('password', { type: 'server', message: msg });
      toast.error(msg);
      return;
    }
    const fallback =
      translateMaybeKey(`auth.errors.${err.code}`) || err.message;
    toast.error(fallback);
    return;
  }
  toast.error(t('auth.errors.unknown'));
}
