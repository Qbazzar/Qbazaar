import { cn } from '@/lib/utils';

import '@/styles/design-tokens-sell.css';

/**
 * The 20 px radio mark of the checkout and promotion rows (CheckBox 367:15366):
 * a ring that fills brand orange around a white dot once the input is checked.
 * Put it right after a `peer sr-only` radio inside the row's label; inside a
 * `group` row its ring also darkens under the pointer.
 */
export function RadioDot({ className }: { className?: string }) {
  return (
    <span
      aria-hidden="true"
      className={cn(
        'flex size-5 shrink-0 items-center justify-center rounded-full border-2 border-(--color-qb-check-ring) transition-colors group-hover:border-(--color-qb-check-ring-hover) peer-checked:border-qb-brand peer-checked:bg-qb-brand [&>span]:invisible peer-checked:[&>span]:visible',
        className,
      )}
    >
      <span className="size-2 rounded-full bg-qb-surface" />
    </span>
  );
}
