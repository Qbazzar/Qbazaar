'use client';

/**
 * FE-2.5 — Verification status page.
 *
 * Shows the four verification channels (email / phone / business / KYC) with
 * checkmark icons. Email + phone are actionable today; business + KYC are
 * placeholders for later sprints.
 */
import { useState } from 'react';
import { useRouter } from 'next/navigation';
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

import { Button } from '@/components/ui/button';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import { getVerificationStatus } from '@/lib/api/account';
import { sendEmailVerification, ApiClientError } from '@/lib/api/auth';
import { useAuth } from '@/hooks/useAuth';
import type { VerificationStatus } from '@/lib/api/types';

const DEFAULT_STATUS: VerificationStatus = {
  email_verified: false,
  phone_verified: false,
  business_verified: false,
  kyc_verified: false,
};

export default function AccountVerificationPage() {
  const router = useRouter();
  const { user } = useAuth();
  const [sendingEmail, setSendingEmail] = useState(false);

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
    if (!user?.phone) return;
    router.push(`/verify-otp?phone=${encodeURIComponent(user.phone)}`);
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

      {isLoading ? (
        <div className="flex justify-center py-10" role="status">
          <Loader2Icon
            className="text-muted-foreground size-5 animate-spin"
            aria-hidden
          />
        </div>
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
              status.phone_verified ? null : (
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
