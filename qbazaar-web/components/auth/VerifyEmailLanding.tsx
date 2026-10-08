'use client';

import { useEffect, useRef, useState, type ReactNode } from 'react';
import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { toast } from 'sonner';
import { CircleCheck, Loader2, TriangleAlert } from 'lucide-react';

import { Button, buttonVariants } from '@/components/design-system/Button';
import { cn } from '@/lib/utils';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import {
  ApiClientError,
  sendEmailVerification,
  verifyEmail,
} from '@/lib/api/auth';
import { authSubmitClass } from './AuthFooter';
import { AuthHeading } from './AuthHeading';

type Status = 'checking' | 'success' | 'expired' | 'missing';

/**
 * Landing page for the Laravel "signed URL" email-verification link.
 *
 * The mail contains a link of the form
 *   /verify-email?id=…&hash=…&signature=…&expires=…
 *
 * We forward the path params + query untouched so the backend can re-validate
 * the cryptographic signature. The component just renders the outcome.
 */
export function VerifyEmailLanding() {
  const router = useRouter();
  const search = useSearchParams();
  const id = (search.get('id') ?? '').trim();
  const hash = (search.get('hash') ?? '').trim();
  const signature = search.get('signature') ?? undefined;
  const expires = search.get('expires') ?? undefined;
  const continueParam = search.get('continue') ?? '/';

  const [status, setStatus] = useState<Status>(() =>
    id && hash ? 'checking' : 'missing',
  );
  const [resending, setResending] = useState(false);
  const attempted = useRef(false);

  useEffect(() => {
    if (status !== 'checking' || attempted.current) return;
    attempted.current = true;
    (async () => {
      try {
        await verifyEmail(id, hash, { signature, expires });
        setStatus('success');
      } catch (err) {
        if (err instanceof ApiClientError && err.status === 410) {
          setStatus('expired');
          return;
        }
        // Any other error — surface a toast but still show the expired card so
        // the user has a clear next step (request a fresh link).
        const message =
          err instanceof ApiClientError
            ? translateMaybeKey(`auth.errors.${err.code}`) || err.message
            : t('auth.errors.unknown');
        toast.error(message);
        setStatus('expired');
      }
    })();
  }, [expires, hash, id, signature, status]);

  const resend = async () => {
    setResending(true);
    try {
      await sendEmailVerification();
      toast.success(t('auth.verify_email.resend_success'));
    } catch (err) {
      const fallback =
        err instanceof ApiClientError
          ? translateMaybeKey(`auth.errors.${err.code}`) || err.message
          : t('auth.verify_email.resend_failed');
      toast.error(fallback);
    } finally {
      setResending(false);
    }
  };

  if (status === 'checking') {
    return (
      <ResultCard
        icon={<Loader2 className="animate-spin motion-reduce:animate-none" />}
        title={t('auth.verify_email.checking_title')}
        body={t('auth.verify_email.checking_body')}
      />
    );
  }

  if (status === 'success') {
    return (
      <ResultCard
        icon={<CircleCheck />}
        title={t('auth.verify_email.success_title')}
        body={t('auth.verify_email.success_body')}
      >
        <Button fullWidth onClick={() => router.replace(continueParam)} className={authSubmitClass}>
          {t('auth.verify_email.continue')}
        </Button>
      </ResultCard>
    );
  }

  if (status === 'expired') {
    return (
      <ResultCard
        icon={<TriangleAlert />}
        title={t('auth.verify_email.expired_title')}
        body={t('auth.verify_email.expired_body')}
      >
        <Button fullWidth onClick={resend} disabled={resending} className={authSubmitClass}>
          {resending ? (
            <>
              <Loader2 className="animate-spin" aria-hidden="true" />
              {t('common.loading')}
            </>
          ) : (
            t('auth.verify_email.resend')
          )}
        </Button>
        <Link href="/login" className={cn(buttonVariants({ variant: 'outline', fullWidth: true }), authSubmitClass)}>
          {t('auth.verify_otp.back_to_login')}
        </Link>
      </ResultCard>
    );
  }

  // status === 'missing'
  return (
    <ResultCard
      icon={<TriangleAlert />}
      title={t('auth.verify_email.missing_params_title')}
      body={t('auth.verify_email.missing_params_body')}
    >
      <Link href="/login" className={cn(buttonVariants({ fullWidth: true }), authSubmitClass)}>
        {t('auth.verify_otp.back_to_login')}
      </Link>
    </ResultCard>
  );
}

/** "Check your email" card content of 739:36548 (the auth layout draws the card) with the result's icon, title and next step. */
function ResultCard({
  icon,
  title,
  body,
  children,
}: {
  icon: ReactNode;
  title: string;
  body: string;
  children?: ReactNode;
}) {
  return (
    <div role="status" className="flex flex-col items-center gap-8">
      <AuthHeading icon={icon} title={title} subtitle={body} />
      {children ? <div className="flex w-full max-w-[420px] flex-col gap-3">{children}</div> : null}
    </div>
  );
}
