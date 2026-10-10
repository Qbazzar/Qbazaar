'use client';

import { useRef, type FormEvent } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { toast } from 'sonner';
import { Loader2 } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { showDesignToast } from '@/components/design-system/design-toast';
import { Field } from '@/components/design-system/Field';
import { focusRing } from '@/components/design-system/focus-ring';
import { Input } from '@/components/design-system/Input';
import { cn } from '@/lib/utils';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import { registerSchema, type RegisterInput } from '@/lib/validation/auth';
import { ApiClientError, register as apiRegister } from '@/lib/api/auth';
import { useAuthStore } from '@/store/auth';
import { AuthErrorCode } from '@/lib/api/types';
import { verifyOtpHref } from '@/lib/auth/phone-gate';
import { AuthFooter, authInputClass, authLinkClass, authSubmitClass } from './AuthFooter';
import { FieldError, fieldErrorText } from './FieldError';
import { PasswordInput } from './PasswordInput';
import { PhoneNumberFields } from './PhoneNumberFields';
import { Turnstile, type TurnstileHandle } from './Turnstile';

const ACCOUNT_TYPES = [
  { value: 'private', labelKey: 'auth.register.account_type_private' },
  { value: 'business', labelKey: 'auth.register.account_type_business' },
] as const;

/** `.qb-sec`: 500 20 px section title. */
const sectionTitle = 'text-qb-h5 font-medium text-qb-auth-section';

/** Links inside `.qb-terms`: orange and underlined, in the sentence's own size and weight. */
const termsLinkClass = cn('rounded-qb-xs text-qb-brand underline hover:text-qb-brand-hover', focusRing);

export function RegisterForm() {
  const router = useRouter();
  const setAuth = useAuthStore((s) => s.setAuth);
  const setHydrated = useAuthStore((s) => s.setHydrated);
  const turnstile = useRef<TurnstileHandle>(null);

  const form = useForm<RegisterInput>({
    resolver: zodResolver(registerSchema),
    defaultValues: {
      full_name: '',
      email: '',
      phone: '',
      password: '',
      account_type: 'private',
      language: 'ar',
      accepted_terms: false as unknown as true,
    },
    mode: 'onBlur',
  });

  // handleSubmit runs inside the event, so the Turnstile ref is never read while rendering.
  const onSubmit = (event: FormEvent<HTMLFormElement>) =>
    form.handleSubmit(async (values) => {
      try {
        const data = await apiRegister(values, await turnstile.current?.getToken());
        setAuth({ user: data.user, accessToken: data.tokens.access_token });
        setHydrated(true);
        showDesignToast(t('auth.register.success'));
        // The reference journey: sign-up → "Verify Your Identity" → number → code.
        router.replace(`${verifyOtpHref(values.phone, '/')}&intro=1`);
      } catch (err) {
        handleSubmitError(err, form);
      } finally {
        turnstile.current?.reset();
      }
    })(event);

  const errors = form.formState.errors;
  const submitting = form.formState.isSubmitting;

  return (
    <form method="post" onSubmit={onSubmit} noValidate className="flex flex-col">
      <fieldset className="mt-[26px]">
        <legend className={sectionTitle}>{t('auth.register.usage_question')}</legend>
        <div className="mt-3.5 flex gap-3.5">
          {ACCOUNT_TYPES.map(({ value, labelKey }) => (
            <label
              key={value}
              className={cn(
                'flex min-w-0 flex-1 cursor-pointer items-center gap-2.5 rounded-qb-md border border-qb-line bg-qb-surface px-4 py-[15px] text-qb-caption text-qb-ink transition-colors',
                'has-[:checked]:border-qb-brand has-[:checked]:bg-qb-brand-soft',
                'has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-qb-brand-active',
              )}
            >
              <input type="radio" value={value} className="size-[18px] shrink-0 accent-qb-brand" {...form.register('account_type')} />
              {t(labelKey)}
            </label>
          ))}
        </div>
      </fieldset>

      <fieldset className="mt-[26px]">
        <legend className={sectionTitle}>{t('auth.register.login_details')}</legend>
        <Field
          label={t('auth.register.full_name_label')}
          required
          error={fieldErrorText(errors.full_name?.message)}
          className="mt-[18px]"
        >
          {(control) => (
            <Input
              {...control}
              type="text"
              autoComplete="name"
              className={authInputClass}
              placeholder={t('auth.register.full_name_placeholder')}
              {...form.register('full_name')}
            />
          )}
        </Field>

        <Field
          label={t('auth.register.email_label')}
          required
          error={fieldErrorText(errors.email?.message)}
          className="mt-[18px]"
        >
          {(control) => (
            <Input
              {...control}
              type="email"
              autoComplete="email"
              dir="ltr"
              className={cn(authInputClass, 'rtl:placeholder:text-right')}
              placeholder={t('auth.register.email_placeholder')}
              {...form.register('email')}
            />
          )}
        </Field>

        <Controller
          control={form.control}
          name="phone"
          render={({ field }) => (
            <PhoneNumberFields
              inputRef={field.ref}
              name={field.name}
              value={field.value}
              onChange={field.onChange}
              onBlur={field.onBlur}
              error={fieldErrorText(errors.phone?.message)}
              className="mt-[18px]"
            />
          )}
        />

        <Field
          label={t('auth.register.password_label')}
          required
          error={fieldErrorText(errors.password?.message)}
          className="mt-[18px]"
        >
          {(control) => (
            <PasswordInput
              {...control}
              autoComplete="new-password"
              placeholder={t('auth.register.password_placeholder')}
              {...form.register('password')}
            />
          )}
        </Field>
      </fieldset>

      <div className="mt-5 flex flex-col gap-1.5">
        <label className="flex items-start gap-2.5 text-start text-qb-body-sm leading-[1.55] text-qb-auth-terms">
          <input
            type="checkbox"
            className="mt-[3px] size-[17px] shrink-0 accent-qb-ink"
            aria-invalid={errors.accepted_terms ? true : undefined}
            aria-describedby={errors.accepted_terms ? 'terms-error' : undefined}
            {...form.register('accepted_terms')}
          />
          <span>
            {t('auth.register.terms_prefix')}{' '}
            <Link href="/p/terms" className={termsLinkClass}>
              {t('auth.register.terms_link')}
            </Link>{' '}
            {t('auth.register.terms_and')}{' '}
            <Link href="/p/privacy" className={termsLinkClass}>
              {t('auth.register.privacy_link')}
            </Link>
            {t('auth.register.terms_suffix')}
          </span>
        </label>
        <FieldError id="terms-error" message={errors.accepted_terms?.message} />
      </div>

      <Turnstile ref={turnstile} />

      <Button
        type="submit"
        fullWidth
        disabled={submitting}
        className={cn(authSubmitClass, 'mt-[26px]', submitting && 'cursor-progress')}
      >
        {submitting ? (
          <>
            <Loader2 className="animate-spin" aria-hidden="true" />
            {t('auth.register.submitting')}
          </>
        ) : (
          t('auth.register.submit_free')
        )}
      </Button>

      <AuthFooter>
        {t('auth.register.have_account')}{' '}
        <Link href="/login" className={authLinkClass}>
          {t('auth.register.go_to_login')}
        </Link>
      </AuthFooter>
    </form>
  );
}

function handleSubmitError(
  err: unknown,
  form: ReturnType<typeof useForm<RegisterInput>>,
) {
  if (err instanceof ApiClientError) {
    if (err.code === AuthErrorCode.ValidationFailed && err.details) {
      const known: (keyof RegisterInput)[] = [
        'full_name',
        'email',
        'phone',
        'password',
        'account_type',
        'language',
        'accepted_terms',
      ];
      for (const [field, messages] of Object.entries(err.details)) {
        if ((known as string[]).includes(field) && messages?.length) {
          form.setError(field as keyof RegisterInput, {
            type: 'server',
            message: messages[0],
          });
        }
      }
      return;
    }
    if (err.code === AuthErrorCode.EmailExists) {
      form.setError('email', {
        type: 'server',
        message: t('auth.errors.AUTH_007'),
      });
      return;
    }
    if (err.code === AuthErrorCode.PhoneExists) {
      form.setError('phone', {
        type: 'server',
        message: t('auth.errors.AUTH_008'),
      });
      return;
    }
    const fallback =
      translateMaybeKey(`auth.errors.${err.code}`) || err.message;
    toast.error(fallback);
    return;
  }
  toast.error(t('auth.errors.unknown'));
}
