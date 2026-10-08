import type { Metadata } from 'next';

import { WithdrawalView } from '@/components/wallet/WithdrawalView';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();
  return { title: t('orders.withdrawal.title') };
}

export default function WithdrawalPage() {
  return <WithdrawalView />;
}
