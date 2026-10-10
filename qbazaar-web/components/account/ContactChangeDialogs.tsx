'use client';

/**
 * "Edit Email Address" (403:14778) and "Edit Phone Number" (402:13904) of
 * account.html. Sign-in is passwordless on the API side, so where the design
 * asks for the password, both ask for the 6-digit security code the API
 * emails to the current address; the phone then confirms the new number
 * with the code texted to it.
 */
import { useEffect, useState, type ReactNode } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Loader2 } from 'lucide-react';

import { showDesignToast } from '@/components/design-system/design-toast';
import { Field } from '@/components/design-system/Field';
import { Input } from '@/components/design-system/Input';
import { FieldError } from '@/components/auth/FieldError';
import { OtpInput } from '@/components/auth/OtpInput';
import { PhoneNumberFields, maskPhoneForCode } from '@/components/auth/PhoneNumberFields';
import { confirmPhoneChange, requestEmailChange, requestPhoneChange, requestReauthCode } from '@/lib/api/account';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import { apiErrorMessage } from './api-error-message';
import { qatarPhoneRegex } from '@/lib/validation/auth';
import { useAuthStore } from '@/store/auth';

import {
  AccountDialog,
  AccountDialogActions,
  accountCancelClass,
  accountInputClass,
  accountSaveClass,
} from './AccountDialog';

const CODE_LENGTH = 6;
const EMAIL_PATTERN = /^[^@\s]+@[^@\s]+\.[^@\s]+$/;

interface ContactDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

export function EditEmailDialog({ open, onOpenChange }: ContactDialogProps) {
  const user = useAuthStore((s) => s.user);
  const [step, setStep] = useState<'email' | 'code'>('email');
  const [email, setEmail] = useState('');
  const [error, setError] = useState<string | null>(null);
  const reauth = useReauthCode();

  const close = (next: boolean) => {
    onOpenChange(next);
    if (!next) {
      setStep('email');
      setEmail('');
      setError(null);
    }
  };

  const toCode = async () => {
    const next = email.trim().toLowerCase();
    if (!EMAIL_PATTERN.test(next)) return setError(t('auth.errors.email_invalid'));
    if (next === user?.email.toLowerCase()) return setError(t('account.contact.same_email'));
    setError(null);
    if (await reauth.send()) setStep('code');
  };

  const confirm = async (code: string) => {
    await requestEmailChange({ email: email.trim().toLowerCase(), reauth_code: code });
    showDesignToast(t('account.contact.email_link_sent', { email: email.trim() }));
    close(false);
  };

  return (
    <AccountDialog open={open} onOpenChange={close} title={t('account.contact.email_title')}>
      {step === 'email' ? (
        <form
          noValidate
          onSubmit={(event) => {
            event.preventDefault();
            void toCode();
          }}
          className="flex flex-col gap-[18px] text-start"
        >
          <div className="rounded-[14px] bg-qb-brand-soft px-5 py-[18px]">
            <p className="mb-2 text-qb-body font-semibold text-qb-ink">{t('account.contact.two_messages')}</p>
            <ul className="list-disc ps-5 text-qb-caption leading-[1.7] text-qb-ink-secondary">
              <li>{t('account.contact.first_message')}</li>
              <li>{t('account.contact.second_message')}</li>
            </ul>
          </div>
          <Field label={t('account.contact.registered_email')} className="mt-1.5">
            {(control) => (
              <Input
                {...control}
                value={user?.email ?? ''}
                disabled
                dir="ltr"
                className={cn(accountInputClass, 'disabled:border-qb-line disabled:bg-qb-acct-tile rtl:text-right')}
              />
            )}
          </Field>
          <Field label={t('account.contact.new_email')} error={error ?? undefined}>
            {(control) => (
              <Input
                {...control}
                type="email"
                autoComplete="email"
                dir="ltr"
                value={email}
                onChange={(event) => {
                  setError(null);
                  setEmail(event.target.value);
                }}
                placeholder="example@gmail.com"
                className={cn(accountInputClass, 'rtl:text-right rtl:placeholder:text-right')}
              />
            )}
          </Field>
          <DialogButtons pending={reauth.sending} onCancel={() => close(false)} />
        </form>
      ) : (
        <SecurityCodeStep
          sentTo={user?.email ?? ''}
          reauth={reauth}
          onConfirm={confirm}
          onCancel={() => close(false)}
        />
      )}
    </AccountDialog>
  );
}

export function EditPhoneDialog({ open, onOpenChange }: ContactDialogProps) {
  const queryClient = useQueryClient();
  const user = useAuthStore((s) => s.user);
  const setUser = useAuthStore((s) => s.setUser);
  const [step, setStep] = useState<'phone' | 'reauth' | 'sms'>('phone');
  const [phone, setPhone] = useState('');
  const [error, setError] = useState<string | null>(null);
  const reauth = useReauthCode();

  const close = (next: boolean) => {
    onOpenChange(next);
    if (!next) {
      setStep('phone');
      setPhone('');
      setError(null);
    }
  };

  const toReauth = async () => {
    if (!qatarPhoneRegex.test(phone)) return setError(t('auth.errors.phone_invalid'));
    if (phone === user?.phone) return setError(t('account.contact.same_phone'));
    setError(null);
    if (await reauth.send()) setStep('reauth');
  };

  const sendSms = async (code: string) => {
    await requestPhoneChange({ phone, reauth_code: code });
    setStep('sms');
  };

  const verifySms = async (code: string) => {
    const profile = await confirmPhoneChange(code);
    if (user) setUser({ ...user, phone: profile.phone, phone_verified: profile.phone_verified });
    void queryClient.invalidateQueries({ queryKey: ['account'] });
    showDesignToast(t('account.contact.phone_changed'));
    close(false);
  };

  return (
    <AccountDialog open={open} onOpenChange={close} title={t('account.contact.phone_title')}>
      {step === 'phone' ? (
        <form
          noValidate
          onSubmit={(event) => {
            event.preventDefault();
            void toReauth();
          }}
          className="text-start"
        >
          <PhoneNumberFields
            value={phone}
            onChange={(next) => {
              setError(null);
              setPhone(next);
            }}
            error={error ?? undefined}
            hideNote
            withChevron
            placeholder={t('account.contact.phone_placeholder')}
          />
          <p className="mt-3.5 text-qb-caption text-qb-ink-faint">{t('account.contact.sms_note')}</p>
          <div className="mt-[26px] flex justify-end">
            <button type="submit" disabled={reauth.sending} className={cn(accountSaveClass, 'flex-none basis-auto px-[26px]')}>
              {reauth.sending ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
              {t('account.contact.confirm_phone')}
            </button>
          </div>
        </form>
      ) : step === 'reauth' ? (
        <SecurityCodeStep sentTo={user?.email ?? ''} reauth={reauth} onConfirm={sendSms} onCancel={() => close(false)} />
      ) : (
        <CodeStep
          intro={t('account.contact.sms_sent', { phone: maskPhoneForCode(phone) })}
          onConfirm={verifySms}
          onCancel={() => close(false)}
        />
      )}
    </AccountDialog>
  );
}

/** Asks the API to email the step-up code, with its resend cooldown. */
function useReauthCode() {
  const [sending, setSending] = useState(false);
  const [resendAt, setResendAt] = useState(0);
  const [now, setNow] = useState(() => Date.now());

  useEffect(() => {
    if (resendAt <= now) return;
    const id = window.setInterval(() => setNow(Date.now()), 1000);
    return () => window.clearInterval(id);
  }, [now, resendAt]);

  const send = async (): Promise<boolean> => {
    setSending(true);
    try {
      const data = await requestReauthCode();
      const at = Date.now();
      setResendAt(at + data.can_resend_in * 1000);
      setNow(at);
      return true;
    } catch (err) {
      toast.error(apiErrorMessage(err));
      return false;
    } finally {
      setSending(false);
    }
  };

  return { send, sending, waitSeconds: Math.max(0, Math.ceil((resendAt - now) / 1000)) };
}

type ReauthCode = ReturnType<typeof useReauthCode>;

function SecurityCodeStep({
  sentTo,
  reauth,
  onConfirm,
  onCancel,
}: {
  sentTo: string;
  reauth: ReauthCode;
  onConfirm: (code: string) => Promise<void>;
  onCancel: () => void;
}) {
  return (
    <CodeStep
      intro={t('account.contact.reauth_sent', { email: sentTo })}
      resend={
        <button
          type="button"
          onClick={() => void reauth.send()}
          disabled={reauth.sending || reauth.waitSeconds > 0}
          className="cursor-pointer font-medium text-qb-brand underline disabled:cursor-not-allowed disabled:opacity-60"
        >
          {t('auth.verify_otp.resend_now')}
          {reauth.waitSeconds > 0 ? ` ${t('auth.verify_otp.resend_wait', { seconds: reauth.waitSeconds })}` : ''}
        </button>
      }
      onConfirm={onConfirm}
      onCancel={onCancel}
    />
  );
}

/** Six code boxes with Save / Cancel; API errors show under the boxes. */
function CodeStep({
  intro,
  resend,
  onConfirm,
  onCancel,
}: {
  intro: string;
  resend?: ReactNode;
  onConfirm: (code: string) => Promise<void>;
  onCancel: () => void;
}) {
  const [code, setCode] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);

  const submit = async () => {
    if (code.length !== CODE_LENGTH) return setError(t('auth.errors.otp_invalid_format'));
    setPending(true);
    setError(null);
    try {
      await onConfirm(code);
    } catch (err) {
      setError(apiErrorMessage(err));
      setPending(false);
    }
  };

  return (
    <form
      noValidate
      onSubmit={(event) => {
        event.preventDefault();
        void submit();
      }}
      className="text-start"
    >
      <p className="text-qb-body-sm text-qb-ink-secondary">{intro}</p>
      <OtpInput
        value={code}
        onChange={(next) => {
          setError(null);
          setCode(next);
        }}
        length={CODE_LENGTH}
        disabled={pending}
        ariaInvalid={Boolean(error)}
        ariaDescribedBy={error ? 'contact-code-error' : undefined}
        autoFocus
        className="mt-6 mb-2"
      />
      <FieldError id="contact-code-error" message={error ?? undefined} />
      {resend ? (
        <p className="mt-3 text-qb-caption text-qb-ink-secondary">
          {t('auth.verify_otp.no_code')} {resend}
        </p>
      ) : null}
      <DialogButtons pending={pending} onCancel={onCancel} />
    </form>
  );
}

function DialogButtons({ pending, onCancel }: { pending: boolean; onCancel: () => void }) {
  return (
    <AccountDialogActions className="mt-[26px]">
      <button type="submit" disabled={pending} className={cn(accountSaveClass, pending && 'cursor-progress')}>
        {pending ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
        {t('common.save')}
      </button>
      <button type="button" onClick={onCancel} disabled={pending} className={accountCancelClass}>
        {t('common.cancel')}
      </button>
    </AccountDialogActions>
  );
}
