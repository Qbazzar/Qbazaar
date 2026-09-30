'use client';

import { useCallback } from 'react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { useAuth } from '@/hooks/useAuth';
import { t } from '@/lib/i18n/messages';
import {
  phoneGateRedirect,
  resolvePhoneGateStatus,
  type PhoneGateStatus,
} from '@/lib/auth/phone-gate';
import { currentLocationPath } from '@/lib/navigation/safe-return-to';

export interface PhoneVerificationGate {
  status: PhoneGateStatus;
  /**
   * Returns true when a phone-gated action may run now. Otherwise sends the
   * user to login or phone verification (returning to `returnTo`) and
   * returns false.
   */
  ensureVerifiedPhone: (returnTo?: string) => boolean;
}

export function usePhoneVerificationGate(): PhoneVerificationGate {
  const router = useRouter();
  const { user, isAuthenticated, isHydrated } = useAuth();
  const status = resolvePhoneGateStatus({ isHydrated, isAuthenticated, user });

  const ensureVerifiedPhone = useCallback(
    (returnTo?: string) => {
      if (status === 'loading') return false;
      const redirect = phoneGateRedirect(status, returnTo ?? currentLocationPath());
      if (!redirect) return true;
      if (status === 'unverified') toast.info(t('auth.phone_gate.toast'));
      router.push(redirect);
      return false;
    },
    [router, status],
  );

  return { status, ensureVerifiedPhone };
}
