import type { Metadata } from 'next';
import { notFound } from 'next/navigation';

import { BuyNowForm } from '@/components/orders/BuyNowForm';
import { DealPageShell } from '@/components/orders/DealPageShell';
import type { DealAd } from '@/lib/api/commerce-types';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';
import { fetchApiData } from '@/lib/seo';

interface PageProps {
  params: Promise<{ id: string }>;
}

export async function generateMetadata({ params }: PageProps): Promise<Metadata> {
  await resolveServerLocale();
  const { id } = await params;
  const ad = await fetchApiData<DealAd>(`/api/v1/ads/${encodeURIComponent(id)}`, 300);
  return {
    title: ad ? `${t('orders.deal.buy_title')} · ${ad.title}` : t('orders.deal.buy_title'),
    robots: { index: false },
  };
}

/** `/ads/{id}/buy`: the Buy Now request for an ad (659:58417). */
export default async function BuyNowPage({ params }: PageProps) {
  await resolveServerLocale();
  const { id } = await params;
  const ad = await fetchApiData<DealAd>(`/api/v1/ads/${encodeURIComponent(id)}`, 300);
  if (!ad) notFound();

  return (
    <DealPageShell ad={ad} current={t('orders.deal.buy_title')}>
      <BuyNowForm ad={ad} />
    </DealPageShell>
  );
}
