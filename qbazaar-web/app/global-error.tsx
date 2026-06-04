'use client';

/**
 * Last-resort boundary for errors thrown in the root layout itself. It must
 * render its own <html>/<body> because it replaces the entire document. Kept
 * dependency-free and Arabic-first so it works even when nothing else does.
 */
export default function GlobalError({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  return (
    <html lang="ar" dir="rtl">
      <body
        style={{
          margin: 0,
          minHeight: '100vh',
          display: 'flex',
          flexDirection: 'column',
          alignItems: 'center',
          justifyContent: 'center',
          gap: 14,
          fontFamily: 'system-ui, sans-serif',
          textAlign: 'center',
          padding: 16,
        }}
      >
        <h1 style={{ margin: 0, fontSize: 26 }}>حدث خطأ ما</h1>
        <p style={{ margin: 0, color: '#555' }}>
          واجهنا مشكلة غير متوقعة. حاول إعادة تحميل الصفحة.
        </p>
        <button
          type="button"
          onClick={reset}
          style={{
            padding: '10px 20px',
            borderRadius: 9999,
            border: 'none',
            background: '#F37335',
            color: '#fff',
            fontWeight: 700,
            cursor: 'pointer',
          }}
        >
          إعادة المحاولة
        </button>
      </body>
    </html>
  );
}
