'use client';

import type { ReactNode } from 'react';
import { usePathname } from 'next/navigation';

const HIDE_FOOTER_PREFIXES = ['/login', '/register', '/forgot-password', '/reset-password', '/verify-otp', '/post-ad'];

/** Hides the server-rendered footer on routes with their own chrome (auth pages, the post-ad wizard). */
export function SiteFooterGate({ children }: { children: ReactNode }) {
  const pathname = usePathname() ?? '/';
  if (HIDE_FOOTER_PREFIXES.some((prefix) => pathname.startsWith(prefix))) return null;
  return children;
}
