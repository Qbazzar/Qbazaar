import type { Metadata } from 'next';

import { CheckoutView } from '@/components/orders/CheckoutView';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';

interface PageProps {
  params: Promise<{ orderId: string }>;
}

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();
  return { title: t('orders.checkout.title'), robots: { index: false } };
}

/** `/checkout/{orderId}`: cash checkout of an order placed from a request or an offer. */
export default async function CheckoutPage({ params }: PageProps) {
  const { orderId } = await params;
  return <CheckoutView orderId={orderId} />;
}
