import type { Metadata } from 'next';

import { PromoteAdView } from '@/components/promotions/PromoteAdView';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';

interface PageProps {
  params: Promise<{ id: string }>;
}

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();
  return { title: t('orders.promotion.page_title') };
}

export default async function PromoteAdPage({ params }: PageProps) {
  const { id } = await params;
  return <PromoteAdView adId={id} />;
}
