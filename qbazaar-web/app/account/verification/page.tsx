'use client';

/**
 * FE-2.5 — Verification status page.
 *
 * Shows the four verification channels (email / phone / business / KYC) with
 * checkmark icons. Email + phone are actionable today; business + KYC are
 * placeholders for later sprints. Phone-gated actions land here with a
 * `continue` path so the user returns to them after verifying.
 */
import { Suspense, useEffect, useState } from 'react';
import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import {
  CheckCircle2Icon,
  CircleIcon,
  Loader2Icon,
  MailIcon,
  PhoneIcon,
  BriefcaseIcon,
  BadgeCheckIcon,
} from 'lucide-react';

import { Button, buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import { getVerificationStatus } from '@/lib/api/account';
import { sendEmailVerification, ApiClientError } from '@/lib/api/auth';
import { useAuth } from '@/hooks/useAuth';
import { useAuthStore } from '@/store/auth';
import {
  PHONE_VERIFICATION_PATH,
  hasVerifiablePhone,
  verifyOtpHref,
} from '@/lib/auth/phone-gate';
import { safeReturnTo } from '@/lib/navigation/safe-return-to';
import type { VerificationStatus } from '@/lib/api/types';

const DEFAULT_STATUS: VerificationStatus = {
  email_verified: false,
  phone_verified: false,
  business_verified: false,
  kyc_verified: false,
};

export default function AccountVerificationPage() {
  // useSearchParams() needs a Suspense boundary for the static build.
  return (
    <Suspense fallback={<LoadingState />}>
      <VerificationContent />
    </Suspense>
  );
}

function VerificationContent() {
  const router = useRouter();
  const search = useSearchParams();
  const { user } = useAuth();
  const setPhoneVerified = useAuthStore((s) => s.setPhoneVerified);
  const [sendingEmail, setSendingEmail] = useState(false);

  const continueParam = search.get('continue');
  const continueTarget = continueParam ? safeReturnTo(continueParam) : null;

  const { data: status = DEFAULT_STATUS, isLoading } = useQuery({
    queryKey: ['account', 'verification-status'],
    queryFn: getVerificationStatus,
    // Seed with what we know from the auth store so the page paints instantly.
    placeholderData: user
      ? {
          email_verified: user.email_verified,
          phone_verified: user.phone_verified,
          business_verified: false,
          kyc_verified: false,
        }
      : DEFAULT_STATUS,
  });

  // Verification may have happened on another device; keep the gates in sync.
  useEffect(() => {
    if (status.phone_verified) setPhoneVerified(true);
  }, [setPhoneVerified, status.phone_verified]);

  const phoneMissing = !hasVerifiablePhone(user?.phone);

  const handleSendEmail = async () => {
    setSendingEmail(true);
    try {
      await sendEmailVerification();
      toast.success(t('account.verification.email_sent'));
    } catch (err) {
      if (err instanceof ApiClientError) {
        toast.error(
          translateMaybeKey(`auth.errors.${err.code}`) ||
            t('account.verification.email_send_failed'),
        );
      } else {
        toast.error(t('account.verification.email_send_failed'));
      }
    } finally {
      setSendingEmail(false);
    }
  };

  const handleVerifyPhone = () => {
    if (!hasVerifiablePhone(user?.phone)) return;
    router.push(verifyOtpHref(user.phone, continueTarget ?? PHONE_VERIFICATION_PATH));
  };

  return (
    <section className="space-y-6">
      <header className="space-y-1.5">
        <h1 className="font-display text-3xl tracking-tight sm:text-4xl">
          {t('account.verification.title')}
        </h1>
        <p className="text-muted-foreground text-sm">
          {t('account.verification.subtitle')}
        </p>
      </header>

      {continueTarget && !isLoading ? (
        <PhoneGateBanner
          verified={status.phone_verified}
          continueTarget={continueTarget}
        />
      ) : null}

      {isLoading ? (
        <LoadingState />
      ) : (
        <ul className="grid gap-3">
          <VerificationRow
            icon={MailIcon}
            title={t('account.verification.channels.email')}
            value={user?.email ?? ''}
            verified={status.email_verified}
            action={
              status.email_verified ? null : (
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={handleSendEmail}
                  disabled={sendingEmail}
                  className="rounded-full px-3 text-xs font-semibold"
                >
                  {sendingEmail ? (
                    <>
                      <Loader2Icon
                        className="size-3.5 animate-spin"
                        aria-hidden
                      />
                      {t('account.verification.ctas.sending_email')}
                    </>
                  ) : (
                    t('account.verification.ctas.send_email')
                  )}
                </Button>
              )
            }
          />

          <VerificationRow
            icon={PhoneIcon}
            title={t('account.verification.channels.phone')}
            value={user?.phone ?? ''}
            verified={status.phone_verified}
            action={
              status.phone_verified ? null : phoneMissing ? (
                <Link
                  href="/support/new"
                  className={cn(
                    buttonVariants({ variant: 'outline', size: 'sm' }),
                    'rounded-full px-3 text-xs font-semibold',
                  )}
                >
                  {t('auth.phone_gate.contact_support')}
                </Link>
              ) : (
                <Button
                  type="button"
                  size="sm"
                  onClick={handleVerifyPhone}
                  className="rounded-full px-3 text-xs font-semibold"
                >
                  {t('account.verification.ctas.verify_phone')}
                </Button>
              )
            }
          />
          {!status.phone_verified && phoneMissing ? (
            <li className="text-muted-foreground px-1 text-xs leading-relaxed">
              <span className="text-ink-900 block font-semibold">
                {t('auth.phone_gate.missing_phone_title')}
              </span>
              {t('auth.phone_gate.missing_phone_body')}
            </li>
          ) : null}

          <VerificationRow
            icon={BriefcaseIcon}
            title={t('account.verification.channels.business')}
            value={null}
            verified={status.business_verified}
            action={
              status.business_verified ? null : (
                <span className="text-muted-foreground text-xs">
                  {t('account.verification.ctas.business_soon')}
                </span>
              )
            }
          />

          <VerificationRow
            icon={BadgeCheckIcon}
            title={t('account.verification.channels.kyc')}
            value={null}
            verified={status.kyc_verified}
            action={
              status.kyc_verified ? null : (
                <span className="text-muted-foreground text-xs">
                  {t('account.verification.ctas.kyc_soon')}
                </span>
              )
            }
          />
        </ul>
      )}
    </section>
  );
}

function LoadingState() {
  return (
    <div className="flex justify-center py-10" role="status">
      <Loader2Icon className="text-muted-foreground size-5 animate-spin" aria-hidden />
    </div>
  );
}

function PhoneGateBanner({
  verified,
  continueTarget,
}: {
  verified: boolean;
  continueTarget: string;
}) {
  return (
    <div
      role="status"
      className="bg-coral/10 flex flex-col items-start gap-3 rounded-2xl p-4 sm:flex-row sm:items-center"
    >
      <div className="min-w-0 flex-1 space-y-1">
        <p className="text-ink-900 text-sm font-semibold">
          {verified
            ? t('auth.phone_gate.verified_title')
            : t('auth.phone_gate.title')}
        </p>
        {verified ? null : (
          <p className="text-muted-foreground text-xs leading-relaxed sm:text-sm">
            {t('auth.phone_gate.body.generic')}{' '}
            {t('auth.phone_gate.body.returning')}
          </p>
        )}
      </div>
      {verified ? (
        <Link
          href={continueTarget}
          className={cn(
            buttonVariants({ size: 'sm' }),
            'shrink-0 rounded-full px-4 text-xs font-semibold',
          )}
        >
          {t('auth.phone_gate.resume')}
        </Link>
      ) : null}
    </div>
  );
}

function VerificationRow({
  icon: Icon,
  title,
  value,
  verified,
  action,
}: {
  icon: React.ComponentType<{ className?: string; 'aria-hidden'?: boolean }>;
  title: string;
  value: string | null;
  verified: boolean;
  action: React.ReactNode;
}) {
  return (
    <li className="bg-card ring-foreground/10 flex flex-col items-start gap-3 rounded-2xl p-4 ring-1 sm:flex-row sm:items-center">
      <span className="bg-muted text-ink-700 inline-flex size-10 shrink-0 items-center justify-center rounded-xl">
        <Icon className="size-5" aria-hidden />
      </span>

      <div className="min-w-0 flex-1">
        <div className="flex items-center gap-2">
          <span className="text-ink-900 text-sm font-semibold">{title}</span>
          {verified ? (
            <span
              className="text-sage inline-flex items-center gap-1 text-xs font-medium"
              aria-label={t('account.verification.status.verified')}
            >
              <CheckCircle2Icon className="size-3.5" aria-hidden />
              <span>{t('account.verification.status.verified')}</span>
            </span>
          ) : (
            <span
              className="text-muted-foreground inline-flex items-center gap-1 text-xs"
              aria-label={t('account.verification.status.not_verified')}
            >
              <CircleIcon className="size-3.5" aria-hidden />
              <span>{t('account.verification.status.not_verified')}</span>
            </span>
          )}
        </div>
        {value ? (
          <span
            className="text-muted-foreground mt-0.5 block truncate text-xs"
            dir="ltr"
          >
            {value}
          </span>
        ) : null}
      </div>

      <div className="shrink-0">{action}</div>
    </li>
  );
}
