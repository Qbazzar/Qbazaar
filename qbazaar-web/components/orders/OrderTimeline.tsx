import { Check } from 'lucide-react';

import { Icon } from '@/components/design-system/Icon';
import type { Order } from '@/lib/api/commerce-types';
import { t } from '@/lib/i18n/messages';
import { formatDateTime, isoDate } from '@/lib/orders/dates';
import { orderTimeline } from '@/lib/orders/status';
import { cn } from '@/lib/utils';

/** The order's steps as a vertical list: done, the current one, and what comes next. */
export function OrderTimeline({ order }: { order: Order }) {
  const steps = orderTimeline(order);

  return (
    <ol className="flex flex-col font-qb">
      {steps.map((step, index) => {
        const isLast = index === steps.length - 1;
        return (
          <li
            key={step.key}
            aria-current={step.state === 'current' ? 'step' : undefined}
            className="relative flex gap-3 pb-6 last:pb-0"
          >
            {!isLast ? (
              <span
                aria-hidden="true"
                className={cn('absolute start-[13px] top-7 bottom-1 w-0.5 rounded-qb-pill', step.state === 'done' ? 'bg-qb-brand' : 'bg-qb-line')}
              />
            ) : null}
            <span
              aria-hidden="true"
              className={cn(
                'relative flex size-7 shrink-0 items-center justify-center rounded-full border-2',
                step.state === 'done' && 'border-qb-brand bg-qb-brand text-white',
                step.state === 'current' && 'border-qb-brand bg-qb-surface',
                step.state === 'upcoming' && 'border-qb-line bg-qb-surface',
              )}
            >
              {step.state === 'done' ? <Icon icon={Check} size="sm" /> : null}
              {step.state === 'current' ? <span className="size-2.5 rounded-full bg-qb-brand" /> : null}
            </span>
            <div className="min-w-0 pt-0.5">
              <p className={cn('text-qb-body font-medium', step.state === 'upcoming' ? 'text-qb-ink-subtle' : 'text-qb-ink')}>
                {t(`orders.detail.step.${step.key}`)}
              </p>
              <p className="mt-0.5 text-qb-caption text-qb-ink-subtle">
                {step.at ? <time dateTime={isoDate(step.at)}>{formatDateTime(step.at)}</time> : t('orders.detail.step_pending')}
              </p>
            </div>
          </li>
        );
      })}
    </ol>
  );
}
