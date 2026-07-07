'use client';

/**
 * Fixed banner shown while an admin is impersonating a user (flag set by the
 * /impersonate landing). Makes the borrowed session obvious and offers a
 * one-click exit that fully logs out and reloads.
 */
import { useEffect, useState } from 'react';
import { logout } from '@/lib/api/auth';
import { clearAuthNonReactive } from '@/store/auth';
import { t } from '@/lib/i18n/messages';

export function ImpersonationBanner() {
  const [name, setName] = useState<string | null>(null);

  useEffect(() => {
    setName(sessionStorage.getItem('qb_impersonating'));
  }, []);

  if (!name) return null;

  const onExit = async () => {
    sessionStorage.removeItem('qb_impersonating');
    try {
      await logout();
    } catch {
      // best-effort — clear locally regardless
    }
    clearAuthNonReactive();
    window.location.href = '/';
  };

  return (
    <div className="fixed inset-x-0 bottom-0 z-50 flex items-center justify-center gap-3 bg-ink-900 px-4 py-2.5 text-sm text-white">
      <span>
        {t('impersonate.banner', { name }, `أنت تتصفّح كـ ${name}`)}
      </span>
      <button
        type="button"
        onClick={onExit}
        className="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold transition hover:bg-white/25"
      >
        {t('impersonate.exit', 'إنهاء الانتحال')}
      </button>
    </div>
  );
}
