import type { Metadata } from 'next';

import { SettlementView } from '@/components/wallet/SettlementView';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();
  return { title: t('orders.settlement.title') };
}

export default function SettlementPage() {
  return <SettlementView />;
}
