import type { Metadata } from 'next';

import { OrderDetailView } from '@/components/orders/OrderDetailView';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';

interface PageProps {
  params: Promise<{ id: string }>;
}

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();
  return { title: t('orders.detail.title') };
}

export default async function OrderPage({ params }: PageProps) {
  const { id } = await params;
  return <OrderDetailView orderId={id} />;
}
