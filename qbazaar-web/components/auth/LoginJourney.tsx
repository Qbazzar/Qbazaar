'use client';

import { Suspense, useState, type ReactNode } from 'react';

import { AuthFormSkeleton } from './AuthFormSkeleton';
import { DeviceVerificationStep, type PendingDeviceCheck } from './DeviceVerificationStep';
import { LoginForm } from './LoginForm';

/**
 * login.html, then verify-identity.html when the API holds the sign-in at
 * its new-device check. The welcome heading is rendered on the server.
 */
export function LoginJourney({ welcome }: { welcome: ReactNode }) {
  const [pending, setPending] = useState<PendingDeviceCheck | null>(null);

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
