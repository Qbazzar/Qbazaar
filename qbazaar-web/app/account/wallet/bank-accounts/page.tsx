import type { Metadata } from 'next';

import { BankAccountsView } from '@/components/wallet/BankAccountsView';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();
  return { title: t('orders.bank.title') };
}

export default function BankAccountsPage() {
  return <BankAccountsView />;
}
