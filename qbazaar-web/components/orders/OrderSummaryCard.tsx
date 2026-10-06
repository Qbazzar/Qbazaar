import type { ReactNode } from 'react';
import { Package } from 'lucide-react';

import { Icon } from '@/components/design-system/Icon';
import { t } from '@/lib/i18n/messages';
import { formatMoney } from '@/lib/orders/money';
import { cn } from '@/lib/utils';

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
    <section
      aria-labelledby="order-summary-title"
      className={cn('rounded-qb-2xl border border-qb-line bg-qb-surface p-4 font-qb shadow-qb-card', className)}
    >
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

      <h2 id="order-summary-title" className="mt-4 text-qb-body font-medium tracking-normal text-qb-ink-body">
        {t('orders.checkout.summary_title')}
      </h2>
      <dl className="mt-4 flex flex-col gap-4 text-qb-caption">
        {lines.map((line) => (
          <div key={line.label} className="flex items-center justify-between gap-4">
            <dt className="text-qb-ink-subtle">{line.label}</dt>
            <dd className="font-semibold text-qb-ink">{line.formatted ? line.amount : formatMoney(line.amount, currency)}</dd>
          </div>
        ))}
        <div className="flex items-center justify-between gap-4 border-t border-qb-line pt-4">
          <dt className="font-medium text-qb-ink-secondary">{t('orders.checkout.total')}</dt>
          <dd className="text-qb-body font-semibold text-qb-ink">{formatMoney(total, currency)}</dd>
        </div>
      </dl>
      {footer ? <div className="mt-4">{footer}</div> : null}
    </section>
  );
}
