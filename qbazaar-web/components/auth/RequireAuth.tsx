'use client';

/**
 * RequireAuth — declarative wrapper around `useRequireAuth`.
 *
 * Use this for client-component pages or sub-trees that need to be gated
 * without writing the spinner/redirect boilerplate themselves. While auth
 * state is still hydrating it paints a small inline spinner; once hydrated,
 * unauthenticated users are bounced to `/login?from=<current>` and `children`
 * is only rendered to authenticated users.
 *
 * For layouts that already render their own skeleton (e.g. `app/account/layout.tsx`)
 * keep calling `useRequireAuth()` directly — this component is for the cases
 * where a top-level spinner is acceptable.
 */
import type { ReactNode } from 'react';
import { Loader2Icon } from 'lucide-react';
import { useRequireAuth } from '@/hooks/useRequireAuth';

interface Props {
  children: ReactNode;
  /** Custom fallback while auth is hydrating. Defaults to a centered spinner. */
  fallback?: ReactNode;
}

export function RequireAuth({ children, fallback }: Props) {
  const { user, isLoading } = useRequireAuth();

  if (isLoading || !user) {
    return (
      fallback ?? (
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
      )
    );
  }

  return <>{children}</>;
}
