import type { Metadata } from 'next';

import { DealPageShell } from '@/components/orders/DealPageShell';
import { dealPageMetadata, loadDealAd } from '@/components/orders/deal-page';
import { MakeOfferForm } from '@/components/orders/MakeOfferForm';
import { t } from '@/lib/i18n/messages';

interface PageProps {
  params: Promise<{ id: string }>;
}

export async function generateMetadata({ params }: PageProps): Promise<Metadata> {
  const { id } = await params;
  return dealPageMetadata(id, 'orders.deal.offer_title');
}

/** `/ads/{id}/offer`: the Make an Offer page for an ad (657:57378). */
export default async function MakeOfferPage({ params }: PageProps) {
  const { id } = await params;
  const ad = await loadDealAd(id);

  return (
    <DealPageShell ad={ad} current={t('orders.deal.offer_title')}>
      <MakeOfferForm ad={ad} />
    </DealPageShell>
  );
}
