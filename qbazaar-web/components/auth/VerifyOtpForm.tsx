'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { CheckCircle2Icon, Loader2Icon } from 'lucide-react';

import { Button, buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import { qatarPhoneRegex } from '@/lib/validation/auth';
import {
  ApiClientError,
  resendOtp,
  sendOtp,
  verifyOtp,
} from '@/lib/api/auth';
import { AuthErrorCode } from '@/lib/api/types';
import { safeReturnTo } from '@/lib/navigation/safe-return-to';
import { PHONE_VERIFICATION_PATH } from '@/lib/auth/phone-gate';
import { useAuthStore } from '@/store/auth';
import { FieldError } from './FieldError';
import { OtpInput } from './OtpInput';

const CODE_LENGTH = 6;
// The API does not expose Retry-After for OTP sends, so after a rate-limit
// response the resend button is held back for one standard cooldown.
const RATE_LIMITED_RESEND_SECONDS = 60;

/**
 * Status machine: 'editing' is the default form, 'success' shows the inline
 * confirmation card. We never auto-navigate so the user can read the result.
 */
type Status = 'editing' | 'success';

export function VerifyOtpForm() {
  const router = useRouter();
  const search = useSearchParams();
  const queryClient = useQueryClient();
  const signedInUser = useAuthStore((s) => s.user);
  const setPhoneVerified = useAuthStore((s) => s.setPhoneVerified);

  const phone = (search.get('phone') ?? '').trim();
  const continueParam = search.get('continue');
  const continueTarget = safeReturnTo(continueParam);
  const resumesAction = continueParam !== null && continueTarget !== '/';

  const phoneIsValid = qatarPhoneRegex.test(phone);

  const [code, setCode] = useState('');
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [status, setStatus] = useState<Status>('editing');

  // Cooldowns are managed as deadlines so background tabs don't drift.
  const [resendDeadline, setResendDeadline] = useState<number | null>(null);
  const [expiresDeadline, setExpiresDeadline] = useState<number | null>(null);
  const [now, setNow] = useState<number>(() => Date.now());
  const initialSendDone = useRef(false);

  // Tick once per second while either timer is active.
  useEffect(() => {
    if (resendDeadline === null && expiresDeadline === null) return;
    const id = window.setInterval(() => setNow(Date.now()), 1000);
    return () => window.clearInterval(id);
  }, [resendDeadline, expiresDeadline]);

  const applyCooldowns = useCallback(
    (canResendIn: number, expiresIn: number) => {
      const t0 = Date.now();
      setResendDeadline(t0 + canResendIn * 1000);
      setExpiresDeadline(t0 + expiresIn * 1000);
      setNow(t0);
    },
    [],
  );

  const handleSendError = useCallback((err: unknown) => {
    if (isRateLimited(err)) {
      setResendDeadline(Date.now() + RATE_LIMITED_RESEND_SECONDS * 1000);
      setNow(Date.now());
      toast.error(t('auth.phone_gate.send_limited'));
      return;
    }
    const message =
      err instanceof ApiClientError
        ? translateMaybeKey(`auth.errors.${err.code}`) || err.message
        : t('auth.verify_otp.send_failed');
    toast.error(message);
  }, []);

  // Auto-request the first OTP when the page loads with a valid phone.
  useEffect(() => {
    if (!phoneIsValid || initialSendDone.current) return;
    initialSendDone.current = true;
    (async () => {
      try {
        const data = await sendOtp({ phone });
        applyCooldowns(data.can_resend_in, data.expires_in);
      } catch (err) {
        handleSendError(err);
      }
    })();
  }, [applyCooldowns, handleSendError, phone, phoneIsValid]);

  const resendSeconds = secondsUntil(resendDeadline, now);
  const expiresSeconds = secondsUntil(expiresDeadline, now);
  const canResend = resendSeconds === 0;

  const submit = useCallback(
    async (codeValue: string) => {
      if (!phoneIsValid) return;
      if (codeValue.length !== CODE_LENGTH) {
        setSubmitError(t('auth.errors.otp_invalid_format'));
        return;
      }
      setSubmitError(null);
      setSubmitting(true);
      try {
        await verifyOtp({ phone, code: codeValue });
        if (signedInUser?.phone === phone) setPhoneVerified(true);
        void queryClient.invalidateQueries({ queryKey: ['account'] });
        setStatus('success');
        toast.success(t('auth.verify_otp.success_title'));
      } catch (err) {
        handleVerifyError(err, {
          setError: (msg) => setSubmitError(msg),
          clearCode: () => setCode(''),
        });
      } finally {
        setSubmitting(false);
      }
    },
    [phone, phoneIsValid, queryClient, setPhoneVerified, signedInUser?.phone],
  );

  const onComplete = useCallback(
    (full: string) => {
      // Avoid retriggering submit on a stale full code (e.g. paste then edit).
      if (submitting || status !== 'editing') return;
      void submit(full);
    },
    [submit, submitting, status],
  );

  const onResend = useCallback(async () => {
    if (!phoneIsValid || !canResend) return;
    setSubmitError(null);
    setCode('');
    try {
      const data = await resendOtp({ phone });
      applyCooldowns(data.can_resend_in, data.expires_in);
      toast.success(t('auth.verify_otp.sent_again'));
    } catch (err) {
      handleSendError(err);
    }
  }, [applyCooldowns, canResend, handleSendError, phone, phoneIsValid]);

  if (!phoneIsValid) {
    return (
      <div className="space-y-4">
        <header className="space-y-2">
          <h1 className="font-display text-3xl tracking-tight">
            {t('auth.verify_otp.missing_phone_title')}
          </h1>
          <p className="text-muted-foreground text-sm">
            {t('auth.verify_otp.missing_phone_body')}
          </p>
        </header>
        <div className="flex gap-2">
          {signedInUser ? (
            <Link
              href={PHONE_VERIFICATION_PATH}
              className={cn(buttonVariants(), 'h-11 rounded-full px-6 text-sm font-semibold')}
            >
              {t('account.verification.title')}
            </Link>
          ) : (
            <Link
              href="/login"
              className={cn(buttonVariants(), 'h-11 rounded-full px-6 text-sm font-semibold')}
            >
              {t('auth.verify_otp.back_to_login')}
            </Link>
          )}
        </div>
      </div>
    );
  }

  if (status === 'success') {
    return (
      <div className="space-y-5">
        <div className="bg-cream-50 border-border flex flex-col items-center gap-3 rounded-2xl border p-6 text-center">
          <span className="bg-coral/15 text-coral inline-flex size-12 items-center justify-center rounded-full">
            <CheckCircle2Icon className="size-6" aria-hidden="true" />
          </span>
          <h1 className="font-display text-2xl tracking-tight">
            {t('auth.verify_otp.success_title')}
          </h1>
          <p className="text-muted-foreground max-w-xs text-sm leading-relaxed">
            {t('auth.verify_otp.success_body')}
          </p>
        </div>
        <Button
          onClick={() => router.replace(continueTarget)}
          size="lg"
          className="h-11 w-full rounded-full text-sm font-semibold"
        >
          {resumesAction
            ? t('auth.phone_gate.resume')
            : t('auth.verify_otp.continue')}
        </Button>
      </div>
    );
  }

  return (
    <form
      onSubmit={(event) => {
        event.preventDefault();
        void submit(code);
      }}
      noValidate
      className="space-y-5"
    >
      <p className="text-muted-foreground text-sm">
        {t('auth.verify_otp.subtitle')}{' '}
        <span className="text-foreground font-medium" dir="ltr">
          {phone}
        </span>
      </p>

      <div className="space-y-2">
        <OtpInput
          value={code}
          onChange={(next) => {
            setSubmitError(null);
            setCode(next);
          }}
          onComplete={onComplete}
          length={CODE_LENGTH}
          disabled={submitting}
          ariaInvalid={Boolean(submitError)}
          ariaDescribedBy={submitError ? 'otp-error' : undefined}
          autoFocus
        />
        <div className="flex min-h-[1.25rem] items-center justify-center">
          <FieldError id="otp-error" message={submitError ?? undefined} />
        </div>
        {expiresSeconds > 0 && !submitError ? (
          <p className="text-muted-foreground text-center text-xs">
            {t('auth.verify_otp.expires_in').replace(
              '{seconds}',
              String(expiresSeconds),
            )}
          </p>
        ) : null}
      </div>

      <Button
        type="submit"
        size="lg"
        disabled={submitting || code.length !== CODE_LENGTH}
        className={cn(
          'h-11 w-full rounded-full text-sm font-semibold',
          submitting && 'cursor-progress',
        )}
      >
        {submitting ? (
          <>
            <Loader2Icon className="size-4 animate-spin" aria-hidden="true" />
            {t('auth.verify_otp.submitting')}
          </>
        ) : (
          t('auth.verify_otp.submit')
        )}
      </Button>

      <p className="text-muted-foreground text-center text-sm">
        {t('auth.verify_otp.resend_question')}{' '}
        {canResend ? (
          <button
            type="button"
            onClick={onResend}
            className="text-coral font-medium hover:underline"
          >
            {t('auth.verify_otp.resend_now')}
          </button>
        ) : (
          <span className="font-medium">
            {t('auth.verify_otp.resend_in').replace(
              '{seconds}',
              String(resendSeconds),
            )}
          </span>
        )}
      </p>
    </form>
  );
}

function secondsUntil(deadline: number | null, now: number): number {
  if (deadline === null) return 0;
  const diff = Math.ceil((deadline - now) / 1000);
  return diff > 0 ? diff : 0;
}

function isRateLimited(err: unknown): boolean {
  return (
    err instanceof ApiClientError &&
    (err.code === AuthErrorCode.AuthRateLimited ||
      err.code === AuthErrorCode.RateLimited)
  );
}

function handleVerifyError(
  err: unknown,
  hooks: { setError: (msg: string) => void; clearCode: () => void },
) {
  if (err instanceof ApiClientError) {
    if (err.code === AuthErrorCode.OtpExpired) {
      const msg = t('auth.errors.AUTH_004');
      hooks.setError(msg);
      hooks.clearCode();
      toast.error(msg);
      return;
    }
    if (err.code === AuthErrorCode.OtpInvalid) {
      const msg = t('auth.errors.AUTH_005');
      hooks.setError(msg);
      toast.error(msg);
      return;
    }
    if (err.code === AuthErrorCode.ValidationFailed && err.details) {
      const codeErrors = err.details.code ?? err.details.phone;
      if (codeErrors?.length) {
        hooks.setError(codeErrors[0]);
        return;
      }
    }
    const fallback =
      translateMaybeKey(`auth.errors.${err.code}`) || err.message;
    hooks.setError(fallback);
    toast.error(fallback);
    return;
  }
  const fallback = t('auth.errors.unknown');
  hooks.setError(fallback);
  toast.error(fallback);
}
