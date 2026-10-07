import type { Metadata } from 'next';

import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';

import { ImpersonateClient } from './ImpersonateClient';

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();

  return { title: t('impersonate.loading'), robots: { index: false, follow: false } };
}

export default function ImpersonatePage() {
  return <ImpersonateClient />;
}
