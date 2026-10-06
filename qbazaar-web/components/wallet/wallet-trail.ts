import type { BreadcrumbItem } from '@/components/design-system/Breadcrumb';
import { t } from '@/lib/i18n/messages';

/** "Wallet › {page}" trail of the wallet sub-pages. */
export function walletTrail(current: string): BreadcrumbItem[] {
  return [{ label: t('orders.wallet.title'), href: '/account/wallet' }, { label: current }];
}
