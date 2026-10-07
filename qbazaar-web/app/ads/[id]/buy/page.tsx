import type { Metadata } from 'next';

import { BuyNowForm } from '@/components/orders/BuyNowForm';
import { DealPageShell } from '@/components/orders/DealPageShell';
import { dealPageMetadata, loadDealAd } from '@/components/orders/deal-page';
import { t } from '@/lib/i18n/messages';

interface PageProps {
  params: Promise<{ id: string }>;
}

export async function generateMetadata({ params }: PageProps): Promise<Metadata> {
  const { id } = await params;
  return dealPageMetadata(id, 'orders.deal.buy_title');
}

/** `/ads/{id}/buy`: the Buy Now request for an ad (659:58417). */
export default async function BuyNowPage({ params }: PageProps) {
  const { id } = await params;
  const ad = await loadDealAd(id);

  return (
    <DealPageShell ad={ad} current={t('orders.deal.buy_title')}>
      <BuyNowForm ad={ad} />
    </DealPageShell>
  );
}
