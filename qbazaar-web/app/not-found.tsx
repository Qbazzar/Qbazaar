import type { Metadata } from 'next';

import { NotFoundView } from '@/components/status/NotFoundView';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();

  return { title: t('errors.not_found_title') };
}

/** 404 for unknown URLs and `notFound()` calls, built like "Search Not Found" (655:55973). */
export default async function NotFound() {
  // Renders in parallel with the root layout, so prime the locale here too.
  await resolveServerLocale();

  return <NotFoundView />;
}
