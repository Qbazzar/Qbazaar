'use client';

import type { ReactNode } from 'react';
import { usePathname } from 'next/navigation';

import { hasOwnChrome } from './own-chrome';

/**
 * Every screen of the reference fades in rising 8 px (`animation: qbFade .3s
 * ease` on each `[data-screen-label]`). The auth pages are static there, so
 * the routes with their own chrome render without it.
 */
export function PageEnter({ children }: { children: ReactNode }) {
  const pathname = usePathname() ?? '/';
  if (hasOwnChrome(pathname)) return children;
  return <div className="animate-qb-page-enter motion-reduce:animate-none">{children}</div>;
}
