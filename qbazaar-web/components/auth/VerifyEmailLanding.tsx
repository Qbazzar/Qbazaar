'use client';

import { useEffect, useRef, useState } from 'react';
import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { toast } from 'sonner';
import {
  AlertTriangleIcon,
  CheckCircle2Icon,
  Loader2Icon,
} from 'lucide-react';

import { Button, buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import {
  ApiClientError,
  sendEmailVerification,
  verifyEmail,
} from '@/lib/api/auth';

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
      <Card
        icon={<Loader2Icon className="size-6 animate-spin" aria-hidden="true" />}
        tone="neutral"
        title={t('auth.verify_email.checking_title')}
        body={t('auth.verify_email.checking_body')}
      />
    );
  }

  if (status === 'success') {
    return (
      <Card
        icon={<CheckCircle2Icon className="size-6" aria-hidden="true" />}
        tone="success"
        title={t('auth.verify_email.success_title')}
        body={t('auth.verify_email.success_body')}
        actions={
          <Button
            size="lg"
            onClick={() => router.replace(continueParam)}
            className="h-11 w-full rounded-full text-sm font-semibold"
          >
            {t('auth.verify_email.continue')}
          </Button>
        }
      />
    );
  }

  if (status === 'expired') {
    return (
      <Card
        icon={<AlertTriangleIcon className="size-6" aria-hidden="true" />}
        tone="warning"
        title={t('auth.verify_email.expired_title')}
        body={t('auth.verify_email.expired_body')}
        actions={
          <div className="flex w-full flex-col gap-2">
            <Button
              size="lg"
              onClick={resend}
              disabled={resending}
              className="h-11 w-full rounded-full text-sm font-semibold"
            >
              {resending ? (
                <>
                  <Loader2Icon
                    className="size-4 animate-spin"
                    aria-hidden="true"
                  />
                  {t('common.loading')}
                </>
              ) : (
                t('auth.verify_email.resend')
              )}
            </Button>
            <Link
              href="/login"
              className={cn(
                buttonVariants({ variant: 'outline' }),
                'h-11 w-full rounded-full text-sm font-semibold',
              )}
            >
              {t('auth.verify_otp.back_to_login')}
            </Link>
          </div>
        }
      />
    );
  }

  // status === 'missing'
  return (
    <Card
      icon={<AlertTriangleIcon className="size-6" aria-hidden="true" />}
      tone="warning"
      title={t('auth.verify_email.missing_params_title')}
      body={t('auth.verify_email.missing_params_body')}
      actions={
        <Link
          href="/login"
          className={cn(
            buttonVariants(),
            'h-11 w-full rounded-full text-sm font-semibold',
          )}
        >
          {t('auth.verify_otp.back_to_login')}
        </Link>
      }
    />
  );
}

function Card({
  icon,
  tone,
  title,
  body,
  actions,
}: {
  icon: React.ReactNode;
  tone: 'neutral' | 'success' | 'warning';
  title: string;
  body: string;
  actions?: React.ReactNode;
}) {
  const toneClasses =
    tone === 'success'
      ? 'bg-coral/15 text-coral'
      : tone === 'warning'
        ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'
        : 'bg-muted text-muted-foreground';

  return (
    <div className="bg-card border-border w-full max-w-md space-y-5 rounded-2xl border p-8 shadow-sm">
      <div className="flex flex-col items-center gap-3 text-center">
        <span
          className={`inline-flex size-12 items-center justify-center rounded-full ${toneClasses}`}
        >
          {icon}
        </span>
        <h1 className="font-display text-2xl tracking-tight">{title}</h1>
        <p className="text-muted-foreground text-sm leading-relaxed">{body}</p>
      </div>
      {actions ? <div className="pt-2">{actions}</div> : null}
    </div>
  );
}
