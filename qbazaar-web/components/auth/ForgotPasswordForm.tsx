'use client';

import { useRef, useState } from 'react';
import Link from 'next/link';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { toast } from 'sonner';
import { Loader2Icon, MailCheckIcon } from 'lucide-react';

import { Button, buttonVariants } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import {
  forgotPasswordSchema,
  type ForgotPasswordInput,
} from '@/lib/validation/auth';
import { ApiClientError, forgotPassword } from '@/lib/api/auth';
import { AuthErrorCode } from '@/lib/api/types';
import { FieldError } from './FieldError';
import { Turnstile, type TurnstileHandle } from './Turnstile';

export function ForgotPasswordForm() {
  /**
   * Anti-enumeration: we always show the success card on a 2xx response, even
   * if the email isn't on file. The backend already returns a generic 202.
   */
  const [submitted, setSubmitted] = useState(false);
  const turnstile = useRef<TurnstileHandle>(null);

  const form = useForm<ForgotPasswordInput>({
    resolver: zodResolver(forgotPasswordSchema),
    defaultValues: { email: '' },
    mode: 'onBlur',
  });

  const onSubmit = form.handleSubmit(async (values) => {
    try {
      await forgotPassword(values, await turnstile.current?.getToken());
      setSubmitted(true);
    } catch (err) {
      handleSubmitError(err, form);
    } finally {
      turnstile.current?.reset();
    }
  });

  if (submitted) {
    return (
      <div className="space-y-5">
        <div className="bg-cream-50 border-border flex flex-col items-center gap-3 rounded-2xl border p-6 text-center">
          <span className="bg-coral/15 text-coral inline-flex size-12 items-center justify-center rounded-full">
            <MailCheckIcon className="size-6" aria-hidden="true" />
          </span>
          <h2 className="font-display text-2xl tracking-tight">
            {t('auth.forgot_password.success_title')}
          </h2>
          <p className="text-muted-foreground max-w-xs text-sm leading-relaxed">
            {t('auth.forgot_password.success_body')}
          </p>
        </div>
        <Link
          href="/login"
          className={cn(
            buttonVariants({ size: 'lg' }),
            'h-11 w-full rounded-full text-sm font-semibold',
          )}
        >
          {t('auth.forgot_password.back_to_login')}
        </Link>
      </div>
    );
  }

  const emailError = form.formState.errors.email?.message;
  const submitting = form.formState.isSubmitting;

  return (
    <form onSubmit={onSubmit} noValidate className="space-y-4">
      <div className="space-y-1.5">
        <Label htmlFor="email">
          {t('auth.forgot_password.email_label')}
        </Label>
        <Input
          id="email"
          type="email"
          autoComplete="email"
          dir="ltr"
          placeholder={t('auth.forgot_password.email_placeholder')}
          aria-invalid={Boolean(emailError)}
          aria-describedby={emailError ? 'email-error' : undefined}
          className="h-10"
          {...form.register('email')}
        />
        <FieldError id="email-error" message={emailError} />
      </div>

      <Turnstile ref={turnstile} />

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
            {t('auth.forgot_password.submitting')}
          </>
        ) : (
          t('auth.forgot_password.submit')
        )}
      </Button>

      <p className="text-muted-foreground text-center text-sm">
        <Link href="/login" className="text-coral font-medium hover:underline">
          {t('auth.forgot_password.back_to_login')}
        </Link>
      </p>
    </form>
  );
}

function handleSubmitError(
  err: unknown,
  form: ReturnType<typeof useForm<ForgotPasswordInput>>,
) {
  if (err instanceof ApiClientError) {
    if (err.code === AuthErrorCode.ValidationFailed && err.details) {
      const emailErrors = err.details.email;
      if (emailErrors?.length) {
        form.setError('email', { type: 'server', message: emailErrors[0] });
        return;
      }
    }
    const fallback =
      translateMaybeKey(`auth.errors.${err.code}`) || err.message;
    toast.error(fallback);
    return;
  }
  toast.error(t('auth.errors.unknown'));
}
