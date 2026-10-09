import type { ReactNode } from 'react';
import { Package } from 'lucide-react';

import { Icon } from '@/components/design-system/Icon';
import { t } from '@/lib/i18n/messages';
import { formatMoney } from '@/lib/orders/money';
import { cn } from '@/lib/utils';

import { panelClass } from './CheckoutPanel';

export interface SummaryLine {
  label: string;
  /** Exact decimal string, or already formatted text such as "Free". */
  amount: string;
  formatted?: boolean;
}

export interface OrderSummaryCardProps {
  title: string;
  subtitle?: string;
  currency: string;
  lines: SummaryLine[];
  total: string;
  /** Button and note under the total. */
  footer?: ReactNode;
  className?: string;
}

/**
 * The order card of the checkout (682:32513): the item, the amounts and the
 * total, with the confirm button underneath.
 */
export function OrderSummaryCard({ title, subtitle, currency, lines, total, footer, className }: OrderSummaryCardProps) {
  return (
    <section aria-labelledby="order-summary-title" className={cn(panelClass, 'p-4', className)}>
      <div className="flex items-center gap-2.5 border-b border-qb-line pb-4">
        <span
          aria-hidden="true"
          className="flex h-[58px] w-[63px] shrink-0 items-center justify-center rounded-qb-sm bg-qb-fill text-qb-ink-subtle"
        >
          <Icon icon={Package} />
        </span>
        <div className="min-w-0">
          <p className="line-clamp-2 text-qb-body font-medium text-qb-ink">
            <bdi>{title}</bdi>
          </p>
          {subtitle ? <p className="mt-1 text-qb-micro text-qb-ink-subtle">{subtitle}</p> : null}
        </div>
      </div>

      {/* The type of checkout.html: a 15 px title, 14 px lines and the total in bold brand orange. */}
      <h2 id="order-summary-title" className="mt-4 font-qb text-qb-body-sm font-semibold tracking-normal text-qb-ink">
        {t('orders.checkout.summary_title')}
      </h2>
      <dl className="mt-3 flex flex-col gap-[11px] text-qb-caption">
        {lines.map((line) => (
          <div key={line.label} className="flex items-center justify-between gap-4">
            <dt className="text-qb-ink-secondary">{line.label}</dt>
            <dd className="font-semibold text-qb-ink">{line.formatted ? line.amount : formatMoney(line.amount, currency)}</dd>
          </div>
        ))}
        <div className="mt-1.5 flex items-center justify-between gap-4 border-t border-qb-line pt-4 text-qb-body">
          <dt className="font-semibold text-qb-ink">{t('orders.checkout.total')}</dt>
          <dd className="font-bold text-qb-brand">{formatMoney(total, currency)}</dd>
        </div>
      </dl>
      {footer ? <div className="mt-4">{footer}</div> : null}
    </section>
  );
}
