import type { Metadata } from 'next';

import { PromoteAdView } from '@/components/promotions/PromoteAdView';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';

interface PageProps {
  params: Promise<{ id: string }>;
  searchParams: Promise<{ type?: string | string[] }>;
}

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();
  return { title: t('orders.promotion.page_title') };
}

export default async function PromoteAdPage({ params, searchParams }: PageProps) {
  const [{ id }, { type }] = await Promise.all([params, searchParams]);
  return <PromoteAdView adId={id} initialType={typeof type === 'string' ? type : undefined} />;
}
