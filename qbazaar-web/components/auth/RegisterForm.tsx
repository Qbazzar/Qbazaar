'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { toast } from 'sonner';
import { EyeIcon, EyeOffIcon, Loader2Icon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import { registerSchema, type RegisterInput } from '@/lib/validation/auth';
import { ApiClientError, register as apiRegister } from '@/lib/api/auth';
import { useAuthStore } from '@/store/auth';
import { AuthErrorCode } from '@/lib/api/types';
import { FieldError } from './FieldError';
import { PhoneInput } from './PhoneInput';
import { PasswordStrengthIndicator } from './PasswordStrengthIndicator';

export function RegisterForm() {
  const router = useRouter();
  const setAuth = useAuthStore((s) => s.setAuth);
  const setHydrated = useAuthStore((s) => s.setHydrated);
  const [showPassword, setShowPassword] = useState(false);

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
      const data = await apiRegister(values);
      setAuth({ user: data.user, accessToken: data.tokens.access_token });
      setHydrated(true);
      toast.success(t('auth.register.success'));
      router.replace('/');
    } catch (err) {
      handleSubmitError(err, form);
    }
  });

  const errors = form.formState.errors;
  const submitting = form.formState.isSubmitting;
  const passwordValue = form.watch('password');

  return (
    <form onSubmit={onSubmit} noValidate className="space-y-4">
      <div className="space-y-1.5">
        <Label htmlFor="full_name">{t('auth.register.full_name_label')}</Label>
        <Input
          id="full_name"
          type="text"
          autoComplete="name"
          placeholder={t('auth.register.full_name_placeholder')}
          aria-invalid={Boolean(errors.full_name)}
          aria-describedby={errors.full_name ? 'full_name-error' : undefined}
          className="h-10"
          {...form.register('full_name')}
        />
        <FieldError id="full_name-error" message={errors.full_name?.message} />
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="email">{t('auth.register.email_label')}</Label>
        <Input
          id="email"
          type="email"
          autoComplete="email"
          dir="ltr"
          placeholder={t('auth.register.email_placeholder')}
          aria-invalid={Boolean(errors.email)}
          aria-describedby={errors.email ? 'email-error' : undefined}
          className="h-10"
          {...form.register('email')}
        />
        <FieldError id="email-error" message={errors.email?.message} />
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="phone">{t('auth.register.phone_label')}</Label>
        <Controller
          control={form.control}
          name="phone"
          render={({ field }) => (
            <PhoneInput
              id="phone"
              name={field.name}
              value={field.value}
              onChange={field.onChange}
              onBlur={field.onBlur}
              ariaInvalid={Boolean(errors.phone)}
              ariaDescribedBy={errors.phone ? 'phone-error' : undefined}
              placeholder={t('auth.register.phone_placeholder')}
            />
          )}
        />
        <FieldError id="phone-error" message={errors.phone?.message} />
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="password">{t('auth.register.password_label')}</Label>
        <div className="relative" dir="ltr">
          <Input
            id="password"
            type={showPassword ? 'text' : 'password'}
            autoComplete="new-password"
            placeholder={t('auth.register.password_placeholder')}
            aria-invalid={Boolean(errors.password)}
            aria-describedby={errors.password ? 'password-error' : undefined}
            className="h-10 pr-10"
            {...form.register('password')}
          />
          <button
            type="button"
            onClick={() => setShowPassword((v) => !v)}
            className="text-muted-foreground hover:text-foreground absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 transition-colors"
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

      <fieldset className="space-y-1.5">
        <legend className="text-sm font-medium leading-none">
          {t('auth.register.account_type_label')}
        </legend>
        <Controller
          control={form.control}
          name="account_type"
          render={({ field }) => (
            <div className="grid grid-cols-2 gap-2">
              <RadioCard
                checked={field.value === 'private'}
                onSelect={() => field.onChange('private')}
                label={t('auth.register.account_type_private')}
              />
              <RadioCard
                checked={field.value === 'business'}
                onSelect={() => field.onChange('business')}
                label={t('auth.register.account_type_business')}
              />
            </div>
          )}
        />
      </fieldset>

      <div className="space-y-1.5">
        <label className="flex items-start gap-2.5 text-sm leading-relaxed text-foreground">
          <input
            type="checkbox"
            className="accent-coral mt-0.5 size-4 rounded border-input"
            {...form.register('accepted_terms')}
            aria-invalid={Boolean(errors.accepted_terms)}
            aria-describedby={errors.accepted_terms ? 'terms-error' : undefined}
          />
          <span className="text-muted-foreground">
            {t('auth.register.terms_prefix')}{' '}
            <Link href="/terms" className="text-coral hover:underline">
              {t('auth.register.terms_link')}
            </Link>{' '}
            {t('auth.register.terms_and')}{' '}
            <Link href="/privacy" className="text-coral hover:underline">
              {t('auth.register.privacy_link')}
            </Link>
            .
          </span>
        </label>
        <FieldError id="terms-error" message={errors.accepted_terms?.message} />
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
            {t('auth.register.submitting')}
          </>
        ) : (
          t('auth.register.submit')
        )}
      </Button>

      <p className="text-muted-foreground text-center text-sm">
        {t('auth.register.have_account')}{' '}
        <Link href="/login" className="text-coral font-medium hover:underline">
          {t('auth.register.go_to_login')}
        </Link>
      </p>
    </form>
  );
}

function RadioCard({
  checked,
  onSelect,
  label,
}: {
  checked: boolean;
  onSelect: () => void;
  label: string;
}) {
  return (
    <button
      type="button"
      onClick={onSelect}
      aria-pressed={checked}
      className={cn(
        'h-10 rounded-lg border text-sm font-medium transition-colors',
        checked
          ? 'border-coral bg-coral/10 text-foreground'
          : 'border-input bg-background text-muted-foreground hover:bg-muted',
      )}
    >
      {label}
    </button>
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
