'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { registerClientNavigator } from '@/lib/navigation/client-navigator';

export function ClientNavigatorBridge() {
  const router = useRouter();

  useEffect(() => registerClientNavigator((href) => router.push(href)), [router]);

  return null;
}
