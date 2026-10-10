'use client';

/**
 * FE-2.5 — Verification status page.
 *
 * Shows the four verification channels (email / phone / business / KYC) as
 * Account Settings rows with a status chip. Email + phone are actionable
 * today; business + KYC are placeholders for later sprints. Phone-gated
 * actions land here with a `continue` path so the user returns to them after
 * verifying.
 */
import { Suspense, useEffect, useState, type ReactNode } from 'react';
import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import { BadgeCheck, Briefcase, Loader2, Mail, Phone } from 'lucide-react';

import { Badge } from '@/components/design-system/Badge';
import { Button, buttonVariants } from '@/components/design-system/Button';
import { showDesignToast } from '@/components/design-system/design-toast';
import { PanelState } from '@/components/account/PanelState';
import { SettingsList, SettingsPanel, SettingsRow } from '@/components/account/SettingsPanel';
import { VerifiedBadge } from '@/components/account/VerifiedBadge';
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

  const {
    data: status = DEFAULT_STATUS,
    isLoading,
    isFetchedAfterMount,
    isPlaceholderData,
    isSuccess,
  } = useQuery({
    queryKey: ['account', 'verification-status'],
    queryFn: getVerificationStatus,
    // A phone-gate redirect usually means the cached flag is stale, so the
    // server answer is always fetched instead of trusting the query cache.
    refetchOnMount: 'always',
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

  // The server is the source of truth: the phone may have been verified on
  // another device, or un-verified since the session started.
  const serverPhoneVerified =
    isSuccess && isFetchedAfterMount && !isPlaceholderData ? status.phone_verified : null;
  useEffect(() => {
    if (serverPhoneVerified !== null) setPhoneVerified(serverPhoneVerified);
  }, [setPhoneVerified, serverPhoneVerified]);

  const phoneMissing = !hasVerifiablePhone(user?.phone);

  const handleSendEmail = async () => {
    setSendingEmail(true);
    try {
      await sendEmailVerification();
      showDesignToast(t('account.verification.email_sent'));
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
    <SettingsPanel title={t('account.verification.title')} description={t('account.verification.subtitle')}>
      {continueTarget && !isLoading ? (
        <PhoneGateBanner verified={status.phone_verified} continueTarget={continueTarget} />
      ) : null}

      {isLoading ? (
        <PanelState loading />
      ) : (
        <SettingsList>
          <VerificationRow
            icon={<Mail />}
            title={t('account.verification.channels.email')}
            value={user?.email ?? ''}
            verified={status.email_verified}
            action={
              status.email_verified ? null : (
                <Button variant="outline" size="sm" onClick={handleSendEmail} disabled={sendingEmail}>
                  {sendingEmail ? (
                    <>
                      <Loader2 className="animate-spin" aria-hidden="true" />
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
            icon={<Phone />}
            title={t('account.verification.channels.phone')}
            value={user?.phone ?? ''}
            verified={status.phone_verified}
            note={
              !status.phone_verified && phoneMissing ? (
                <>
                  <span className="block font-semibold text-qb-ink">{t('auth.phone_gate.missing_phone_title')}</span>
                  {t('auth.phone_gate.missing_phone_body')}
                </>
              ) : null
            }
            action={
              status.phone_verified ? null : phoneMissing ? (
                <Link href="/support/new" className={buttonVariants({ variant: 'outline', size: 'sm' })}>
                  {t('auth.phone_gate.contact_support')}
                </Link>
              ) : (
                <Button size="sm" onClick={handleVerifyPhone}>
                  {t('account.verification.ctas.verify_phone')}
                </Button>
              )
            }
          />

          <VerificationRow
            icon={<Briefcase />}
            title={t('account.verification.channels.business')}
            value={null}
            verified={status.business_verified}
            action={status.business_verified ? null : <ComingSoon label={t('account.verification.ctas.business_soon')} />}
          />

          <VerificationRow
            icon={<BadgeCheck />}
            title={t('account.verification.channels.kyc')}
            value={null}
            verified={status.kyc_verified}
            action={status.kyc_verified ? null : <ComingSoon label={t('account.verification.ctas.kyc_soon')} />}
          />
        </SettingsList>
      )}
    </SettingsPanel>
  );
}

function LoadingState() {
  return <PanelState loading />;
}

function ComingSoon({ label }: { label: string }) {
  return <span className="text-qb-label text-qb-ink-subtle">{label}</span>;
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
      className="mb-6 flex flex-col items-start gap-3 rounded-qb-xl border border-qb-brand/30 bg-qb-brand-soft p-4 qb-tablet:flex-row qb-tablet:items-center qb-tablet:px-6"
    >
      <div className="min-w-0 flex-1">
        <p className="text-qb-body font-semibold text-qb-ink">
          {verified ? t('auth.phone_gate.verified_title') : t('auth.phone_gate.title')}
        </p>
        {verified ? null : (
          <p className="mt-1 text-qb-caption text-qb-ink-body">
            {t('auth.phone_gate.body.generic')} {t('auth.phone_gate.body.returning')}
          </p>
        )}
      </div>
      {verified ? (
        <Link href={continueTarget} className={buttonVariants({ size: 'sm' })}>
          {t('auth.phone_gate.resume')}
        </Link>
      ) : null}
    </div>
  );
}

function VerificationRow({
  icon,
  title,
  value,
  verified,
  note,
  action,
}: {
  icon: ReactNode;
  title: string;
  value: string | null;
  verified: boolean;
  note?: ReactNode;
  action: ReactNode;
}) {
  return (
    <SettingsRow
      icon={icon}
      label={title}
      value={
        <span className="flex flex-wrap items-center gap-2">
          {value ? (
            <span dir="ltr" className="break-all">
              {value}
            </span>
          ) : null}
          {verified ? (
            <VerifiedBadge />
          ) : (
            <Badge tone="neutral" size="sm" font="label">
              {t('account.verification.status.not_verified')}
            </Badge>
          )}
        </span>
      }
      description={note}
      action={action}
    />
  );
}
