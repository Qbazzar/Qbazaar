'use client';

/**
 * Account layout — wraps every authenticated `/account/...` page.
 *
 * - Sidebar on `lg+`, horizontal tabs on smaller screens (handled inside the
 *   sidebar component itself).
 * - Client-side guard via `useRequireAuth`. While the store hydrates we paint
 *   a skeleton so the page doesn't flash empty.
 *
 * Wave 2 will move this guard to a server component once the bootstrap
 * refresh round-trip runs on the edge.
 */
import type { ReactNode } from 'react';
import { usePathname } from 'next/navigation';
import { Loader2Icon } from 'lucide-react';
import { AccountSidebar } from '@/components/account/AccountSidebar';
import { useRequireAuth } from '@/hooks/useRequireAuth';
import { cn } from '@/lib/utils';

export default function AccountLayout({ children }: { children: ReactNode }) {
  const { user, isLoading } = useRequireAuth();
  const pathname = usePathname();
  // The messages page is a chat workspace — give it a much wider shell than the
  // form-centric pages (which read better at a constrained width).
  const wide = pathname?.startsWith('/account/messages') ?? false;

  if (isLoading || !user) {
    return (
      <div
        className="flex min-h-svh items-center justify-center"
        role="status"
        aria-live="polite"
      >
        <Loader2Icon
          className="text-muted-foreground size-6 animate-spin"
          aria-hidden="true"
        />
      </div>
    );
  }

  return (
    <div className="bg-cream-50 min-h-svh">
      <div
        className={cn(
          'mx-auto w-full px-4 py-6 sm:px-6 sm:py-10',
          wide ? 'max-w-[1600px]' : 'max-w-6xl',
        )}
      >
        <div className="grid gap-6 lg:grid-cols-[260px_minmax(0,1fr)]">
          <aside className="lg:sticky lg:top-6 lg:self-start">
            <AccountSidebar />
          </aside>
          <main className="min-w-0">{children}</main>
        </div>
      </div>
    </div>
  );
}
