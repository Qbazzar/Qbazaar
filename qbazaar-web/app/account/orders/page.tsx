import { Suspense } from 'react';
import type { Metadata } from 'next';

import { OrdersListView } from '@/components/orders/OrdersListView';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();
  return { title: t('orders.list.title') };
}

/** The tabs and the status filter live in the query string, so the client view needs a Suspense boundary. */
export default function OrdersPage() {
  return (
    <Suspense fallback={null}>
      <OrdersListView />
    </Suspense>
  );
}
