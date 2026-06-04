'use client';

import { useEffect } from 'react';

import { t } from '@/lib/i18n/messages';

/**
 * Route-level error boundary. Catches render/runtime errors in any page so the
 * user sees a recoverable message instead of a blank screen.
 */
export default function Error({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  useEffect(() => {
    // Surface to the console (and any wired error tracker) for diagnosis.
    console.error(error);
  }, [error]);

  return (
    <main
      style={{
        minHeight: '60vh',
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        gap: 14,
        padding: '48px 16px',
        textAlign: 'center',
      }}
    >
      <h1 style={{ margin: 0, fontSize: 26, fontWeight: 800 }}>
        {t('errors.generic_title', 'حدث خطأ ما')}
      </h1>
      <p style={{ margin: 0, maxWidth: 460, color: 'var(--ink-700)' }}>
        {t('errors.generic_body', 'واجهنا مشكلة غير متوقعة. حاول مرة أخرى.')}
      </p>
      <button
        type="button"
        onClick={reset}
        className="btn btn--primary btn--pill"
      >
        {t('errors.retry', 'إعادة المحاولة')}
      </button>
    </main>
  );
}
