'use client';

import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { toast } from 'sonner';
import { Loader2 } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { Field } from '@/components/design-system/Field';
import { focusRing } from '@/components/design-system/focus-ring';
import { Input } from '@/components/design-system/Input';
import { cn } from '@/lib/utils';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import { loginSchema, type LoginInput } from '@/lib/validation/auth';
import { ApiClientError, login } from '@/lib/api/auth';
import { useAuthStore } from '@/store/auth';
import { AuthErrorCode } from '@/lib/api/types';
import { safeReturnTo } from '@/lib/navigation/safe-return-to';
import { AuthFooter, authLinkClass, authSubmitClass } from './AuthFooter';
import { announcedError } from './FieldError';
import { PasswordInput } from './PasswordInput';

export function LoginForm() {
  const router = useRouter();
  const search = useSearchParams();
  const setAuth = useAuthStore((s) => s.setAuth);
  const setHydrated = useAuthStore((s) => s.setHydrated);

  const form = useForm<LoginInput>({
    resolver: zodResolver(loginSchema),
    defaultValues: { identifier: '', password: '' },
    mode: 'onBlur',
  });

  const onSubmit = form.handleSubmit(async (values) => {
    try {
      const data = await login(values);
      setAuth({ user: data.user, accessToken: data.tokens.access_token });
      setHydrated(true);
      toast.success(t('auth.login.success'));

      router.replace(safeReturnTo(search.get('from')));
    } catch (err) {
      handleSubmitError(err, form);
    }
  });

  const errors = form.formState.errors;
  const submitting = form.formState.isSubmitting;

  // Sticky lifecycle banner — set when the user just deactivated or deleted
  // their account on `/account/data` and we bounced them here.
  const lifecycleNotice =
    search.get('deactivated') === '1'
      ? t('auth.login.deactivated_notice')
      : search.get('deleted') === '1'
        ? t('auth.login.deleted_notice')
        : null;

  return (
    <form onSubmit={onSubmit} noValidate className="mt-8 flex flex-col gap-6">
      {lifecycleNotice ? (
        <p
          role="status"
          className="rounded-qb-md border border-qb-brand/30 bg-qb-brand-soft px-4 py-3 text-qb-caption text-qb-ink-body"
        >
          {lifecycleNotice}
        </p>
      ) : null}

      <Field label={t('auth.login.identifier_label')} required error={announcedError(errors.identifier?.message)}>
        {(control) => (
          <Input
            {...control}
            type="text"
            autoComplete="username"
            dir="ltr"
            placeholder={t('auth.login.identifier_placeholder')}
            {...form.register('identifier')}
          />
        )}
      </Field>

      <div className="flex flex-col gap-4">
        <Field label={t('auth.login.password_label')} required error={announcedError(errors.password?.message)}>
          {(control) => (
            <PasswordInput
              {...control}
              autoComplete="current-password"
              placeholder={t('auth.login.password_placeholder')}
              {...form.register('password')}
            />
          )}
        </Field>
        <Link
          href="/forgot-password"
          className={cn(
            'self-end rounded-qb-xs text-qb-caption font-medium text-qb-danger hover:underline qb-tablet:text-qb-body',
            focusRing,
          )}
        >
          {t('auth.login.forgot')}
        </Link>
      </div>

      <Button type="submit" fullWidth disabled={submitting} className={cn(authSubmitClass, submitting && 'cursor-progress')}>
        {submitting ? (
          <>
            <Loader2 className="animate-spin" aria-hidden="true" />
            {t('auth.login.submitting')}
          </>
        ) : (
          t('auth.login.submit')
        )}
      </Button>

      <AuthFooter>
        {t('auth.login.no_account')}{' '}
        <Link href="/register" className={authLinkClass}>
          {t('auth.login.go_to_register')}
        </Link>
      </AuthFooter>
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
