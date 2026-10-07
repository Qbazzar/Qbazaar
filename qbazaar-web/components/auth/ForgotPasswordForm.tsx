'use client';

import { useRef, useState } from 'react';
import Link from 'next/link';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { toast } from 'sonner';
import { Loader2, Mail, MailCheck } from 'lucide-react';

import { Button, buttonVariants } from '@/components/design-system/Button';
import { Field } from '@/components/design-system/Field';
import { Input } from '@/components/design-system/Input';
import { cn } from '@/lib/utils';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import {
  forgotPasswordSchema,
  type ForgotPasswordInput,
} from '@/lib/validation/auth';
import { ApiClientError, forgotPassword } from '@/lib/api/auth';
import { AuthErrorCode } from '@/lib/api/types';
import { AuthFooter, authLinkClass, authSubmitClass } from './AuthFooter';
import { AuthHeading } from './AuthHeading';
import { announcedError } from './FieldError';
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
      <div role="status" className="flex flex-col items-center gap-8">
        <AuthHeading
          icon={<MailCheck />}
          title={t('auth.forgot_password.success_title')}
          subtitle={t('auth.forgot_password.success_body')}
        />
        <Link href="/login" className={cn(buttonVariants({ fullWidth: true }), authSubmitClass, 'max-w-[420px]')}>
          {t('auth.forgot_password.back_to_login')}
        </Link>
      </div>
    );
  }

  const emailError = form.formState.errors.email?.message;
  const submitting = form.formState.isSubmitting;

  return (
    <>
      <AuthHeading icon={<Mail />} title={t('auth.forgot_password.title')} subtitle={t('auth.forgot_password.subtitle')} />
      <form onSubmit={onSubmit} noValidate className="mt-8 flex flex-col gap-6">
        <Field label={t('auth.forgot_password.email_label')} required error={announcedError(emailError)}>
          {(control) => (
            <Input
              {...control}
              type="email"
              autoComplete="email"
              dir="ltr"
              placeholder={t('auth.forgot_password.email_placeholder')}
              {...form.register('email')}
            />
          )}
        </Field>

        <Turnstile ref={turnstile} />

        <Button type="submit" fullWidth disabled={submitting} className={cn(authSubmitClass, submitting && 'cursor-progress')}>
          {submitting ? (
            <>
              <Loader2 className="animate-spin" aria-hidden="true" />
              {t('auth.forgot_password.submitting')}
            </>
          ) : (
            t('auth.forgot_password.submit')
          )}
        </Button>

        <AuthFooter>
          <Link href="/login" className={authLinkClass}>
            {t('auth.forgot_password.back_to_login')}
          </Link>
        </AuthFooter>
      </form>
    </>
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
