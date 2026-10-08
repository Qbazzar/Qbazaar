'use client';

import type { ReactNode } from 'react';
import { usePathname } from 'next/navigation';

import { hasOwnChrome } from './own-chrome';

/** Hides the server-rendered footer on routes with their own chrome (the auth pages). */
export function SiteFooterGate({ children }: { children: ReactNode }) {
  const pathname = usePathname() ?? '/';
  return hasOwnChrome(pathname) ? null : children;
}
