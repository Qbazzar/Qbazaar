'use client';

/**
 * Client-side providers wrapper.
 *
 * - Installs the axios auth interceptors exactly once on first mount.
 * - Hosts a TanStack QueryClient that's ready for Wave 2's data fetching.
 */
import { useEffect, useState } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ReactQueryDevtools } from '@tanstack/react-query-devtools';
import { NuqsAdapter } from 'nuqs/adapters/next/app';
import { installAuthInterceptors } from '@/lib/api/interceptors';
import { AuthBootstrap } from '@/components/auth/AuthBootstrap';

export function Providers({ children }: { children: React.ReactNode }) {
  // Lazy-create once per mount so SSR + hydration don't share state.
  const [queryClient] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: {
            staleTime: 30_000,
            refetchOnWindowFocus: false,
            retry: 1,
          },
        },
      }),
  );

  useEffect(() => {
    installAuthInterceptors();
  }, []);

  return (
    <QueryClientProvider client={queryClient}>
      <AuthBootstrap />
      <NuqsAdapter>{children}</NuqsAdapter>
      {process.env.NODE_ENV === 'development' ? (
        <ReactQueryDevtools initialIsOpen={false} buttonPosition="bottom-left" />
      ) : null}
    </QueryClientProvider>
  );
}
