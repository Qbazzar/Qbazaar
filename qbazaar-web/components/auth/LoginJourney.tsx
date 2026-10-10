'use client';

import { Suspense, useEffect, useState, type ReactNode } from 'react';
import { toast } from 'sonner';

import { t } from '@/lib/i18n/messages';

import { AuthFormSkeleton } from './AuthFormSkeleton';
import { DeviceVerificationStep, type PendingDeviceCheck } from './DeviceVerificationStep';
import { LoginForm } from './LoginForm';

/** How long the password may wait in memory for the "Resend" of the device check. */
export const DEVICE_CHECK_TTL_MS = 5 * 60 * 1000;

/**
 * login.html, then verify-identity.html when the API holds the sign-in at
 * its new-device check. The welcome heading is rendered on the server.
 */
export function LoginJourney({ welcome }: { welcome: ReactNode }) {
  const [pending, setPending] = useState<PendingDeviceCheck | null>(null);

  const credentials = pending?.credentials;

  // The password is only kept so "Resend" can sign in again; after a while the
  // user starts over instead, and unmounting drops it with the state.
  useEffect(() => {
    if (!credentials) return;
    const id = window.setTimeout(() => {
      setPending(null);
      toast.error(t('auth.errors.AUTH_012'));
    }, DEVICE_CHECK_TTL_MS);
    return () => window.clearTimeout(id);
  }, [credentials]);

  // useSearchParams() in the forms needs a Suspense boundary so the page can stream during static generation.
  if (pending) {
    return (
      <Suspense fallback={null}>
        <DeviceVerificationStep pending={pending} onChallenge={setPending} onRestart={() => setPending(null)} />
      </Suspense>
    );
  }

  return (
    <>
      {welcome}
      <Suspense fallback={<AuthFormSkeleton fields={2} />}>
        <LoginForm onDeviceCheck={setPending} />
      </Suspense>
    </>
  );
}
