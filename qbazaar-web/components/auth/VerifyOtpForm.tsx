'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Loader2 } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { cn } from '@/lib/utils';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import { qatarPhoneRegex } from '@/lib/validation/auth';
import { ApiClientError, resendOtp, sendOtp, verifyOtp } from '@/lib/api/auth';
import { AuthErrorCode } from '@/lib/api/types';
import { safeReturnTo } from '@/lib/navigation/safe-return-to';
import { useAuthStore } from '@/store/auth';
import { authLinkClass, authSubmitClass } from './AuthFooter';
import { AuthHeading } from './AuthHeading';
import { FieldError } from './FieldError';
import { OtpInput } from './OtpInput';
import { PhoneNumberFields, maskPhoneForCode } from './PhoneNumberFields';
import { Turnstile, type TurnstileHandle } from './Turnstile';

const CODE_LENGTH = 6;
// The API does not expose Retry-After for OTP sends, so after a rate-limit
// response the resend button is held back for one standard cooldown.
const RATE_LIMITED_RESEND_SECONDS = 60;

/**
 * The verification journey of the reference: signup-verify.html ("Continue"),
 * enter-number.html (country + number + "Send"), enter-code.html (the six
 * boxes, "Edit" back to the number). Sign-up starts on the first screen
 * (`?intro=1`); the account's "Verify phone" opens straight on the code.
 */
type Step = 'intro' | 'number' | 'code';

export function VerifyOtpForm() {
  const router = useRouter();
  const search = useSearchParams();
  const queryClient = useQueryClient();
  const signedInUser = useAuthStore((s) => s.user);
  const setPhoneVerified = useAuthStore((s) => s.setPhoneVerified);

  const linkPhone = (search.get('phone') ?? '').trim();
  const continueTarget = safeReturnTo(search.get('continue'));
  const linkPhoneIsValid = qatarPhoneRegex.test(linkPhone);

  const [step, setStep] = useState<Step>(() => {
    if (!linkPhoneIsValid) return 'number';
    return search.get('intro') === '1' ? 'intro' : 'code';
  });
  const [phone, setPhone] = useState(linkPhoneIsValid ? linkPhone : '');
  const [sentTo, setSentTo] = useState<string | null>(null);
  const [numberProblem, setNumberProblem] = useState<'invalid' | 'not_yours' | null>(null);
  const [sending, setSending] = useState(false);
  const [code, setCode] = useState('');
  const [codeError, setCodeError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  // The cooldown is a deadline so background tabs don't drift.
  const [resendDeadline, setResendDeadline] = useState<number | null>(null);
  const [now, setNow] = useState<number>(() => Date.now());
  const autoSendDone = useRef(false);
  const turnstile = useRef<TurnstileHandle>(null);

  useEffect(() => {
    if (resendDeadline === null) return;
    const id = window.setInterval(() => setNow(Date.now()), 1000);
    return () => window.clearInterval(id);
  }, [resendDeadline]);

  const resendSeconds = secondsUntil(resendDeadline, now);

  const startCooldown = useCallback((seconds: number) => {
    const at = Date.now();
    setResendDeadline(at + seconds * 1000);
    setNow(at);
  }, []);

  const handleSendError = useCallback(
    (err: unknown) => {
      if (isRateLimited(err)) {
        startCooldown(RATE_LIMITED_RESEND_SECONDS);
        toast.error(t('auth.phone_gate.send_limited'));
        return;
      }
      toast.error(
        err instanceof ApiClientError
          ? translateMaybeKey(`auth.errors.${err.code}`) || err.message
          : t('auth.verify_otp.send_failed'),
      );
    },
    [startCooldown],
  );

  /** Texts a code to `target` and opens the code screen; `resend` uses the stricter resend endpoint. */
  const sendCode = useCallback(
    async (target: string, resend = false) => {
      setSending(true);
      try {
        const token = await turnstile.current?.getToken();
        const data = resend ? await resendOtp({ phone: target }, token) : await sendOtp({ phone: target }, token);
        startCooldown(data.can_resend_in);
        setSentTo(target);
        setCode('');
        setCodeError(null);
        setStep('code');
        if (resend) toast.success(t('auth.verify_otp.sent_again'));
      } catch (err) {
        handleSendError(err);
      } finally {
        turnstile.current?.reset();
        setSending(false);
      }
    },
    [handleSendError, startCooldown],
  );

  // Opened on the code screen: text the first code right away.
  useEffect(() => {
    if (step !== 'code' || autoSendDone.current || sentTo !== null) return;
    autoSendDone.current = true;
    void sendCode(phone);
  }, [phone, sendCode, sentTo, step]);

  const submitNumber = () => {
    if (!qatarPhoneRegex.test(phone)) {
      setNumberProblem('invalid');
      return;
    }
    // A signed-in account verifies its own number; another one is changed in the account settings first.
    if (signedInUser && signedInUser.phone !== phone) {
      setNumberProblem('not_yours');
      return;
    }
    setNumberProblem(null);
    if (phone === sentTo && resendSeconds > 0) {
      setStep('code');
      return;
    }
    void sendCode(phone);
  };

  const submitCode = async (value: string) => {
    if (value.length !== CODE_LENGTH) {
      setCodeError(t('auth.errors.otp_invalid_format'));
      return;
    }
    setCodeError(null);
    setSubmitting(true);
    try {
      await verifyOtp({ phone, code: value });
      if (signedInUser?.phone === phone) setPhoneVerified(true);
      void queryClient.invalidateQueries({ queryKey: ['account'] });
      toast.success(t('auth.verify_otp.success_title'));
      router.replace(continueTarget);
    } catch (err) {
      handleVerifyError(err, {
        setError: setCodeError,
        clearCode: () => setCode(''),
      });
      setSubmitting(false);
    }
  };

  return (
    <>
      {step === 'intro' ? (
        <div className="text-center">
          <AuthHeading title={t('auth.verify_otp.heading')} subtitle={t('auth.verify_otp.intro')} />
          <Button
            fullWidth
            onClick={() => setStep('number')}
            className={cn(authSubmitClass, 'mx-auto mt-[34px] max-w-[420px]')}
          >
            {t('auth.verify_otp.continue')}
          </Button>
        </div>
      ) : null}

      {step === 'number' ? (
        <>
          <AuthHeading title={t('auth.verify_otp.heading')} subtitle={t('auth.verify_otp.intro')} />
          <form
            method="post"
            noValidate
            onSubmit={(event) => {
              event.preventDefault();
              submitNumber();
            }}
            className="flex flex-col"
          >
            <PhoneNumberFields
              value={phone}
              onChange={(next) => {
                setNumberProblem(null);
                setPhone(next);
              }}
              error={
                numberProblem === 'invalid'
                  ? t('auth.errors.phone_invalid')
                  : numberProblem === 'not_yours'
                    ? t('auth.verify_otp.change_in_settings')
                    : undefined
              }
              className="mt-[26px]"
            />
            {numberProblem === 'not_yours' ? (
              <Link href="/account/security" className={cn(authLinkClass, 'mt-2 self-start text-qb-caption')}>
                {t('account.nav.account_settings')}
              </Link>
            ) : null}
            <Button
              type="submit"
              fullWidth
              disabled={sending}
              className={cn(authSubmitClass, 'mt-[26px]', sending && 'cursor-progress')}
            >
              {sending ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
              {t('auth.verify_otp.send')}
            </Button>
          </form>
        </>
      ) : null}

      {step === 'code' ? (
        <div className="text-center">
          <AuthHeading
            title={t('auth.verify_otp.heading')}
            subtitle={
              <>
                {t('auth.verify_otp.subtitle')}{' '}
                <span dir="ltr">{maskPhoneForCode(phone)}</span>{' '}
                <button
                  type="button"
                  onClick={() => setStep('number')}
                  className={cn(authLinkClass, 'cursor-pointer text-qb-body')}
                >
                  {t('common.edit')}
                </button>
                <br />
                {t('auth.verify_otp.enter_below')}
              </>
            }
          />
          <form
            method="post"
            onSubmit={(event) => {
              event.preventDefault();
              void submitCode(code);
            }}
            noValidate
          >
            <OtpInput
              value={code}
              onChange={(next) => {
                setCodeError(null);
                setCode(next);
              }}
              onComplete={(full) => {
                if (!submitting) void submitCode(full);
              }}
              length={CODE_LENGTH}
              disabled={submitting}
              ariaInvalid={Boolean(codeError)}
              ariaDescribedBy={codeError ? 'otp-error' : undefined}
              autoFocus
              className="mt-[34px] mb-1.5"
            />
            <FieldError id="otp-error" message={codeError ?? undefined} />

            <p className="mt-[18px] text-start text-qb-body font-medium text-qb-auth-resend">
              {t('auth.verify_otp.no_code')}{' '}
              <button
                type="button"
                onClick={() => void sendCode(phone, true)}
                disabled={resendSeconds > 0 || sending}
                className="cursor-pointer rounded-qb-xs underline disabled:cursor-not-allowed disabled:opacity-60"
              >
                {t('auth.verify_otp.resend_now')}
              </button>
              {resendSeconds > 0 ? (
                <span className="ms-1 tabular-nums">{t('auth.verify_otp.resend_wait', { seconds: resendSeconds })}</span>
              ) : null}
            </p>

            <Button
              type="submit"
              fullWidth
              disabled={submitting || code.length !== CODE_LENGTH}
              className={cn(authSubmitClass, 'mx-auto mt-[30px] max-w-[420px]', submitting && 'cursor-progress')}
            >
              {submitting ? (
                <>
                  <Loader2 className="animate-spin" aria-hidden="true" />
                  {t('auth.verify_otp.submitting')}
                </>
              ) : (
                t('auth.verify_otp.submit')
              )}
            </Button>
          </form>
        </div>
      ) : null}

      <Turnstile ref={turnstile} />
    </>
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
