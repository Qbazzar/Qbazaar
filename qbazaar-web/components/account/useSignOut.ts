'use client';

import { useTransition } from 'react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';

import { useAuth } from '@/hooks/useAuth';
import { t } from '@/lib/i18n/messages';

/** Signs out, confirms with a toast and lands on the login page even when the API call fails. */
export function useSignOut() {
  const router = useRouter();
  const { logout } = useAuth();
  const [pending, startTransition] = useTransition();

  const signOut = () => {
    startTransition(async () => {
      try {
        await logout();
        toast.success(t('account.nav.sign_out_confirm'));
      } finally {
        router.replace('/login');
      }
    });
  };

  return { signOut, pending };
}
