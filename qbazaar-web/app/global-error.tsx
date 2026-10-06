'use client';

import { buttonVariants } from '@/components/design-system/Button';
import { cn } from '@/lib/utils';

import './globals.css';

/**
 * Last-resort boundary for errors thrown in the root layout itself. It
 * replaces the whole document, so it renders its own <html>/<body>, loads the
 * global styles itself and depends on no provider. The locale is unknown at
 * this point, so the message is shown in Arabic and English.
 */
export default function GlobalError({ unstable_retry }: { error: Error & { digest?: string }; unstable_retry: () => void }) {
  return (
    <html lang="ar" dir="rtl">
      <body className="flex min-h-svh items-center justify-center bg-qb-page p-4 font-qb text-qb-ink">
        <title>حدث خطأ ما · Something went wrong</title>
        <main className="w-full max-w-[538px] rounded-qb-2xl border border-qb-line bg-qb-surface px-6 py-10 text-center shadow-qb-card">
          <h1 className="font-qb text-qb-h3 font-semibold tracking-normal">حدث خطأ ما</h1>
          <p className="mt-2 text-qb-body text-qb-ink-secondary">واجهنا مشكلة غير متوقعة. حاول مرة أخرى.</p>
          <div lang="en" dir="ltr" className="mt-6 border-t border-qb-line pt-6">
            <p className="font-qb text-qb-h5 font-semibold">Something went wrong</p>
            <p className="mt-2 text-qb-body text-qb-ink-secondary">We hit an unexpected problem. Please try again.</p>
          </div>
          <button type="button" onClick={() => unstable_retry()} className={cn(buttonVariants(), 'mt-8')}>
            إعادة المحاولة · Try again
          </button>
        </main>
      </body>
    </html>
  );
}
