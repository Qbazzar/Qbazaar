import { toast } from 'sonner';

import { ApiClientError } from '@/lib/api/auth';
import { AuthErrorCode } from '@/lib/api/types';
import { t, translateMaybeKey } from '@/lib/i18n/messages';

/**
 * Maps a rejected 6-digit code to the field error (and a toast): expired
 * codes clear the boxes, wrong ones keep them for a correction.
 */
export function handleCodeError(
  err: unknown,
  hooks: { setError: (msg: string) => void; clearCode: () => void },
) {
  if (err instanceof ApiClientError) {
    if (err.code === AuthErrorCode.OtpExpired) {
      const msg = t('auth.errors.AUTH_004');
      hooks.setError(msg);
      hooks.clearCode();
      toast.error(msg);
      return;
    }
    if (err.code === AuthErrorCode.OtpInvalid) {
      const msg = t('auth.errors.AUTH_005');
      hooks.setError(msg);
      toast.error(msg);
      return;
    }
    if (err.code === AuthErrorCode.ValidationFailed && err.details) {
      const codeErrors = err.details.code ?? err.details.phone;
      if (codeErrors?.length) {
        hooks.setError(codeErrors[0]);
        return;
      }
    }
    const fallback =
      translateMaybeKey(`auth.errors.${err.code}`) || err.message;
    hooks.setError(fallback);
    toast.error(fallback);
    return;
  }
  const fallback = t('auth.errors.unknown');
  hooks.setError(fallback);
  toast.error(fallback);
}
