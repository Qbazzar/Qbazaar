import type { Metadata } from 'next';

import { PromotionsView } from '@/components/promotions/PromotionsView';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();
  return { title: t('orders.promotion.title') };
}

export default function PromotionsPage() {
  return <PromotionsView />;
}
