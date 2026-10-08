'use client';

/**
 * Fixed banner shown while an admin is impersonating a user (flag set by the
 * /impersonate landing). Makes the borrowed session obvious and offers a
 * one-click exit that fully logs out and reloads.
 */
import { useEffect, useState } from 'react';

import { Button } from '@/components/design-system/Button';
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
    <div className="fixed inset-x-0 bottom-0 z-50 flex items-center justify-center gap-3 bg-qb-ink px-4 py-2.5 font-qb text-qb-caption text-qb-surface">
      <span>
        {t('impersonate.banner', { name }, `أنت تتصفّح كـ ${name}`)}
      </span>
      {/* The brand focus colour is too dark on the ink bar. */}
      <Button variant="outline" size="sm" onClick={onExit} className="h-8 rounded-qb-pill px-3 text-qb-label focus-visible:outline-qb-surface">
        {t('impersonate.exit', 'إنهاء الانتحال')}
      </Button>
    </div>
  );
}
