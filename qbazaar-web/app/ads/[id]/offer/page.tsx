import type { Metadata } from 'next';
import { notFound } from 'next/navigation';

import { DealPageShell } from '@/components/orders/DealPageShell';
import { MakeOfferForm } from '@/components/orders/MakeOfferForm';
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
    title: ad ? `${t('orders.deal.offer_title')} · ${ad.title}` : t('orders.deal.offer_title'),
    robots: { index: false },
  };
}

/** `/ads/{id}/offer`: the Make an Offer page for an ad (657:57378). */
export default async function MakeOfferPage({ params }: PageProps) {
  await resolveServerLocale();
  const { id } = await params;
  const ad = await fetchApiData<DealAd>(`/api/v1/ads/${encodeURIComponent(id)}`, 300);
  if (!ad) notFound();

  return (
    <DealPageShell ad={ad} current={t('orders.deal.offer_title')}>
      <MakeOfferForm ad={ad} />
    </DealPageShell>
  );
}
