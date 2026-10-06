'use client';

import { useRef } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { toast } from 'sonner';
import { Loader2 } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { Field } from '@/components/design-system/Field';
import { Input } from '@/components/design-system/Input';
import { cn } from '@/lib/utils';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import { registerSchema, type RegisterInput } from '@/lib/validation/auth';
import { ApiClientError, register as apiRegister } from '@/lib/api/auth';
import { useAuthStore } from '@/store/auth';
import { AuthErrorCode } from '@/lib/api/types';
import { AuthFooter, authLinkClass, authSubmitClass } from './AuthFooter';
import { FieldError, announcedError } from './FieldError';
import { PasswordInput } from './PasswordInput';
import { PhoneInput } from './PhoneInput';
import { PasswordStrengthIndicator } from './PasswordStrengthIndicator';
import { Turnstile, type TurnstileHandle } from './Turnstile';

const ACCOUNT_TYPES = [
  { value: 'private', labelKey: 'auth.register.account_type_private' },
  { value: 'business', labelKey: 'auth.register.account_type_business' },
] as const;

const sectionTitle = 'text-qb-body-lg font-medium text-qb-ink-title qb-tablet:text-qb-h5';

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

  const onSubmit = form.handleSubmit(async (values) => {
    try {
      const data = await apiRegister(values, await turnstile.current?.getToken());
      setAuth({ user: data.user, accessToken: data.tokens.access_token });
      setHydrated(true);
      toast.success(t('auth.register.success'));
      router.replace('/');
    } catch (err) {
      handleSubmitError(err, form);
    } finally {
      turnstile.current?.reset();
    }
  });

  const errors = form.formState.errors;
  const submitting = form.formState.isSubmitting;
  const passwordValue = form.watch('password');

  return (
    <form onSubmit={onSubmit} noValidate className="mt-8 flex flex-col gap-6">
      <fieldset>
        <legend className={sectionTitle}>{t('auth.register.usage_question')}</legend>
        <div className="mt-3.5 grid gap-3 qb-tablet:grid-cols-2 qb-tablet:gap-3.5">
          {ACCOUNT_TYPES.map(({ value, labelKey }) => (
            <label
              key={value}
              className={cn(
                'flex h-11 cursor-pointer items-center gap-2.5 rounded-qb-md border border-qb-line bg-qb-surface px-4 text-qb-caption text-qb-ink transition-colors',
                'has-[:checked]:border-qb-brand has-[:checked]:bg-qb-brand-soft',
                'has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-qb-brand-active',
              )}
            >
              <input type="radio" value={value} className="size-[18px] accent-qb-brand" {...form.register('account_type')} />
              {t(labelKey)}
            </label>
          ))}
        </div>
      </fieldset>

      <fieldset>
        <legend className={cn(sectionTitle, 'mb-5')}>{t('auth.register.login_details')}</legend>
        <div className="flex flex-col gap-6">
          <Field label={t('auth.register.full_name_label')} required error={announcedError(errors.full_name?.message)}>
            {(control) => (
              <Input
                {...control}
                type="text"
                autoComplete="name"
                placeholder={t('auth.register.full_name_placeholder')}
                {...form.register('full_name')}
              />
            )}
          </Field>

          <Field label={t('auth.register.email_label')} required error={announcedError(errors.email?.message)}>
            {(control) => (
              <Input
                {...control}
                type="email"
                autoComplete="email"
                dir="ltr"
                placeholder={t('auth.register.email_placeholder')}
                {...form.register('email')}
              />
            )}
          </Field>

          <Field label={t('auth.register.phone_label')} required error={announcedError(errors.phone?.message)}>
            {(control) => (
              <Controller
                control={form.control}
                name="phone"
                render={({ field }) => (
                  <PhoneInput
                    ref={field.ref}
                    id={control.id}
                    name={field.name}
                    value={field.value}
                    onChange={field.onChange}
                    onBlur={field.onBlur}
                    required
                    ariaInvalid={control['aria-invalid']}
                    ariaDescribedBy={control['aria-describedby']}
                    placeholder={t('auth.register.phone_placeholder')}
                  />
                )}
              />
            )}
          </Field>

          <Field label={t('auth.register.password_label')} required error={announcedError(errors.password?.message)}>
            {(control) => (
              <div className="flex flex-col gap-3">
                <PasswordInput
                  {...control}
                  autoComplete="new-password"
                  placeholder={t('auth.register.password_placeholder')}
                  {...form.register('password')}
                />
                <PasswordStrengthIndicator password={passwordValue ?? ''} />
              </div>
            )}
          </Field>
        </div>
      </fieldset>

      <div className="flex flex-col gap-1.5">
        <label className="flex items-start gap-2.5 text-qb-caption leading-relaxed text-qb-ink-subtle qb-tablet:text-qb-body-sm">
          <input
            type="checkbox"
            className="mt-1 size-[17px] shrink-0 accent-qb-ink"
            aria-invalid={errors.accepted_terms ? true : undefined}
            aria-describedby={errors.accepted_terms ? 'terms-error' : undefined}
            {...form.register('accepted_terms')}
          />
          <span>
            {t('auth.register.terms_prefix')}{' '}
            <Link href="/terms" className={authLinkClass}>
              {t('auth.register.terms_link')}
            </Link>{' '}
            {t('auth.register.terms_and')}{' '}
            <Link href="/privacy" className={authLinkClass}>
              {t('auth.register.privacy_link')}
            </Link>
            .
          </span>
        </label>
        <FieldError id="terms-error" message={errors.accepted_terms?.message} />
      </div>

      <Turnstile ref={turnstile} />

      <Button type="submit" fullWidth disabled={submitting} className={cn(authSubmitClass, submitting && 'cursor-progress')}>
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
