'use client';

import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Loader2 } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { showDesignToast } from '@/components/design-system/design-toast';
import { ApiClientError, login, verifyNewDevice, type DeviceChallenge } from '@/lib/api/auth';
import { AuthErrorCode } from '@/lib/api/types';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import type { LoginInput } from '@/lib/validation/auth';
import { cn } from '@/lib/utils';

import { authSubmitClass } from './AuthFooter';
import { AuthHeading } from './AuthHeading';
import { FieldError } from './FieldError';
import { OtpInput } from './OtpInput';
import { handleCodeError } from './otp-errors';
import { useCompleteSignIn } from './useCompleteSignIn';

const CODE_LENGTH = 6;

/** A password sign-in the API held at its new-device check, with what is needed to send a fresh code. */
export interface PendingDeviceCheck {
  challenge: DeviceChallenge;
  credentials: LoginInput;
  remember: boolean;
}

interface DeviceVerificationStepProps {
  pending: PendingDeviceCheck;
  /** A fresh challenge after "Resend". */
  onChallenge: (next: PendingDeviceCheck) => void;
  /** Back to the login form, e.g. when the challenge expired. */
  onRestart: () => void;
}

/** "+974 ••• ••45", the design's mask, from the API's masked number. */
function maskedPhone(sentTo: string): string {
  const lastTwo = sentTo.replace(/\D/g, '').slice(-2);
  return `+974 ••• ••${lastTwo}`;
}

/**
 * verify-identity.html after a login: six boxes for the code the API texted
 * to the account's phone, the red "Resend" line and "Verify". "Resend" signs
 * in again, which is how the API issues a new challenge and code.
 */
export function DeviceVerificationStep({ pending, onChallenge, onRestart }: DeviceVerificationStepProps) {
  const completeSignIn = useCompleteSignIn();
  const [code, setCode] = useState('');
  const [codeError, setCodeError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [resending, setResending] = useState(false);
  const [resendAt, setResendAt] = useState(() => Date.now() + pending.challenge.can_resend_in * 1000);
  const [now, setNow] = useState(() => Date.now());

  useEffect(() => {
    if (resendAt <= now) return;
    const id = window.setInterval(() => setNow(Date.now()), 1000);
    return () => window.clearInterval(id);
  }, [now, resendAt]);

  const resendSeconds = Math.max(0, Math.ceil((resendAt - now) / 1000));

  const submit = async (value: string) => {
    if (value.length !== CODE_LENGTH) {
      setCodeError(t('auth.errors.otp_invalid_format'));
      return;
    }
    setCodeError(null);
    setSubmitting(true);
    try {
      const data = await verifyNewDevice(
        { challenge_token: pending.challenge.challenge_token, code: value },
        { remember: pending.remember },
      );
      completeSignIn(data);
    } catch (err) {
      setSubmitting(false);
      if (err instanceof ApiClientError && err.code === AuthErrorCode.DeviceChallengeInvalid) {
        toast.error(t('auth.errors.AUTH_012'));
        onRestart();
        return;
      }
      handleCodeError(err, { setError: setCodeError, clearCode: () => setCode('') });
    }
  };

  const resend = async () => {
    setResending(true);
    try {
      const result = await login(pending.credentials, { remember: pending.remember });
      if (result.status === 'signed_in') {
        completeSignIn(result.data);
        return;
      }
      setCode('');
      setCodeError(null);
      const at = Date.now();
      setResendAt(at + result.challenge.can_resend_in * 1000);
      setNow(at);
      onChallenge({ ...pending, challenge: result.challenge });
      showDesignToast(t('auth.verify_otp.sent_again'));
    } catch (err) {
      toast.error(
        err instanceof ApiClientError
          ? translateMaybeKey(`auth.errors.${err.code}`) || err.message
          : t('auth.verify_otp.send_failed'),
      );
    } finally {
      setResending(false);
    }
  };

  return (
    <div className="text-center">
      <AuthHeading
        title={t('auth.verify_otp.heading')}
        subtitle={t('auth.device_check.subtitle', { phone: `⁦${maskedPhone(pending.challenge.sent_to)}⁩` })}
      />
      <form
        method="post"
        noValidate
        onSubmit={(event) => {
          event.preventDefault();
          void submit(code);
        }}
      >
        <OtpInput
          value={code}
          onChange={(next) => {
            setCodeError(null);
            setCode(next);
          }}
          onComplete={(full) => {
            if (!submitting) void submit(full);
          }}
          length={CODE_LENGTH}
          disabled={submitting}
          ariaInvalid={Boolean(codeError)}
          ariaDescribedBy={codeError ? 'device-code-error' : undefined}
          autoFocus
          className="mt-[34px] mb-1.5"
        />
        <FieldError id="device-code-error" message={codeError ?? undefined} />

        <p className="mt-[18px] text-start text-qb-body font-medium text-qb-auth-resend">
          {t('auth.verify_otp.no_code')}{' '}
          <button
            type="button"
            onClick={() => void resend()}
            disabled={resendSeconds > 0 || resending || submitting}
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
  );
}
