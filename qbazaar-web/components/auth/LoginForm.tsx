'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
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
import { AuthErrorCode } from '@/lib/api/types';
import { AuthFooter, authInputClass, authLinkClass, authSubmitClass } from './AuthFooter';
import type { PendingDeviceCheck } from './DeviceVerificationStep';
import { fieldErrorText } from './FieldError';
import { PasswordInput } from './PasswordInput';
import { SocialSignIn } from './SocialSignIn';
import { useCompleteSignIn } from './useCompleteSignIn';

interface LoginFormProps {
  /** The API held the sign-in at its new-device check (202): verify-identity.html takes over. */
  onDeviceCheck: (pending: PendingDeviceCheck) => void;
}

export function LoginForm({ onDeviceCheck }: LoginFormProps) {
  const search = useSearchParams();
  const completeSignIn = useCompleteSignIn();
  // Unticked as in login.html: the sign-in then ends when the browser closes.
  const [remember, setRemember] = useState(false);

  const form = useForm<LoginInput>({
    resolver: zodResolver(loginSchema),
    defaultValues: { identifier: '', password: '' },
    mode: 'onBlur',
  });

  const onSubmit = form.handleSubmit(async (values) => {
    try {
      const result = await login(values, { remember });
      if (result.status === 'device_check') {
        onDeviceCheck({ challenge: result.challenge, credentials: values, remember });
        return;
      }
      completeSignIn(result.data);
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
    <>
      <SocialSignIn />
      {/* method="post" keeps the credentials out of the URL if the form is sent before React takes over. */}
      <form method="post" onSubmit={onSubmit} noValidate className="mt-[18px] flex flex-col">
        {lifecycleNotice ? (
          <p
            role="status"
            className="mb-[18px] rounded-qb-md border border-qb-brand/30 bg-qb-brand-soft px-4 py-3 text-qb-caption text-qb-ink-body"
          >
            {lifecycleNotice}
          </p>
        ) : null}

        <Field label={t('auth.login.identifier_label')} required error={fieldErrorText(errors.identifier?.message)}>
          {(control) => (
            <Input
              {...control}
              type="text"
              autoComplete="username"
              dir="ltr"
              className={cn(authInputClass, 'rtl:placeholder:text-right')}
              placeholder={t('auth.login.identifier_placeholder')}
              {...form.register('identifier')}
            />
          )}
        </Field>

        <Field
          label={t('auth.login.password_label')}
          required
          error={fieldErrorText(errors.password?.message)}
          className="mt-[18px]"
        >
          {(control) => (
            <PasswordInput
              {...control}
              autoComplete="current-password"
              placeholder={t('auth.login.password_placeholder')}
              {...form.register('password')}
            />
          )}
        </Field>

        <div className="mt-4 flex items-center justify-between gap-3">
          <label className="flex cursor-pointer items-center gap-[9px] text-qb-body-sm text-qb-auth-check">
            <input
              type="checkbox"
              name="remember"
              checked={remember}
              onChange={(event) => setRemember(event.target.checked)}
              className={cn('size-[18px] shrink-0 cursor-pointer accent-qb-brand', focusRing)}
            />
            {t('auth.login.remember')}
          </label>
          <Link
            href="/forgot-password"
            className={cn('rounded-qb-xs text-qb-body-sm font-medium text-qb-brand hover:underline', focusRing)}
          >
            {t('auth.login.forgot')}
          </Link>
        </div>

        <Button
          type="submit"
          fullWidth
          disabled={submitting}
          className={cn(authSubmitClass, 'mt-[26px]', submitting && 'cursor-progress')}
        >
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
    </>
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
