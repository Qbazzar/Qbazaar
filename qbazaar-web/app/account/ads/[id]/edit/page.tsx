import { Suspense } from 'react';
import type { Metadata } from 'next';

import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';

import { EditAdClient } from './EditAdClient';

interface PageProps {
  params: Promise<{ id: string }>;
}

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();
  return { title: t('post_ad.edit_title') };
}

/**
 * `/account/ads/{id}/edit` — server shell that hands off to the client
 * island, which runs the auth guard, loads the ad and opens the post-ad flow.
 */
export default async function EditAdPage({ params }: PageProps) {
  const { id } = await params;
  return (
    <Suspense fallback={null}>
      <EditAdClient adId={id} />
    </Suspense>
  );
}
