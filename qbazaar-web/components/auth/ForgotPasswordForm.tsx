'use client';

import { useRef, useState, type FormEvent } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { toast } from 'sonner';
import { Loader2 } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
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
import { authInputClass, authLinkClass, authSubmitClass } from './AuthFooter';
import { AuthHeading } from './AuthHeading';
import { fieldErrorText } from './FieldError';
import { Turnstile, type TurnstileHandle } from './Turnstile';

const RESEND_TIPS = ['spam', 'address', 'delay'] as const;

/**
 * forgot-password.html, then send-code.html ("Check your email") once the
 * link is on its way. The API answers the same whether or not the email has
 * an account, so the second card always shows.
 */
export function ForgotPasswordForm() {
  const [sentTo, setSentTo] = useState<string | null>(null);
  const [resending, setResending] = useState(false);
  const turnstile = useRef<TurnstileHandle>(null);

  const form = useForm<ForgotPasswordInput>({
    resolver: zodResolver(forgotPasswordSchema),
    defaultValues: { email: '' },
    mode: 'onBlur',
  });

  const send = async (values: ForgotPasswordInput) => {
    try {
      await forgotPassword(values, await turnstile.current?.getToken());
      return true;
    } catch (err) {
      handleSubmitError(err, form);
      return false;
    } finally {
      turnstile.current?.reset();
    }
  };

  // handleSubmit runs inside the event, so the Turnstile ref is never read while rendering.
  const onSubmit = (event: FormEvent<HTMLFormElement>) =>
    form.handleSubmit(async (values) => {
      if (await send(values)) setSentTo(values.email);
    })(event);

  const resend = async () => {
    if (!sentTo) return;
    setResending(true);
    if (await send({ email: sentTo })) toast.success(t('auth.forgot_password.resent'));
    setResending(false);
  };

  const emailError = form.formState.errors.email?.message;
  const submitting = form.formState.isSubmitting;

  return (
    <>
      {sentTo ? (
        <div role="status">
          <AuthHeading
            align="start"
            title={t('auth.forgot_password.success_title')}
            subtitle={
              <>
                {t('auth.forgot_password.success_sent_to')}{' '}
                {/* Back to the form, for the "make sure the address is correct" tip. */}
                <button
                  type="button"
                  onClick={() => setSentTo(null)}
                  className={cn(authLinkClass, 'cursor-pointer text-qb-body')}
                  dir="ltr"
                >
                  {sentTo}
                </button>
                .
                <br />
                {t('auth.forgot_password.success_click_link')}
              </>
            }
          />
          <p className="mt-[26px] text-qb-body font-medium text-qb-auth-muted">{t('auth.forgot_password.tips_title')}</p>
          <ul className="ms-0.5 mt-[18px] list-disc text-qb-body leading-[1.6] text-qb-auth-muted">
            {RESEND_TIPS.map((tip) => (
              <li key={tip} className="ms-5 mt-3">
                {t(`auth.forgot_password.tips.${tip}`)}
              </li>
            ))}
          </ul>
          <Button
            fullWidth
            onClick={resend}
            disabled={resending}
            className={cn(authSubmitClass, 'mt-[26px]', resending && 'cursor-progress')}
          >
            {resending ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
            {t('auth.forgot_password.resend')}
          </Button>
        </div>
      ) : (
        <>
          <AuthHeading title={t('auth.forgot_password.title')} subtitle={t('auth.forgot_password.subtitle')} />
          <form method="post" onSubmit={onSubmit} noValidate className="flex flex-col">
            <Field
              label={t('auth.forgot_password.email_label')}
              required
              error={fieldErrorText(emailError)}
              className="mt-[26px]"
            >
              {(control) => (
                <Input
                  {...control}
                  type="email"
                  autoComplete="email"
                  dir="ltr"
                  className={cn(authInputClass, 'rtl:placeholder:text-right')}
                  placeholder={t('auth.forgot_password.email_placeholder')}
                  {...form.register('email')}
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
                  {t('auth.forgot_password.submitting')}
                </>
              ) : (
                t('auth.forgot_password.submit')
              )}
            </Button>
          </form>
        </>
      )}
      <Turnstile ref={turnstile} />
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
