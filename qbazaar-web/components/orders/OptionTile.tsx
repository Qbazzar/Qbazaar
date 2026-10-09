import type { InputHTMLAttributes, ReactNode } from 'react';

import { cn } from '@/lib/utils';

import '@/styles/design-tokens-sell.css';

export interface OptionTileProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'type' | 'children'> {
  label: ReactNode;
  /** Read out with the label but not shown: the tiles of the reference are one line. */
  srDescription?: ReactNode;
  /** Shown under the label, for a choice that needs its details on screen (a saved address). */
  details?: ReactNode;
  /** Decorative brand icon at the start. */
  icon?: ReactNode;
  className?: string;
}

/**
 * The compact option tile of checkout.html's payment methods: brand icon,
 * 14 px label and a radio on the end side; the chosen tile turns peach with
 * an orange border. A native radio inside the label, so arrow keys, focus and
 * form submission work without script. Group the tiles in a
 * `role="radiogroup"` labelled by the section heading.
 */
export function OptionTile({ label, srDescription, details, icon, className, ...input }: OptionTileProps) {
  return (
    <label
      className={cn(
        'relative flex min-h-[70px] cursor-pointer items-center gap-3 rounded-qb-lg border border-qb-line bg-qb-surface p-3.5 font-qb transition-colors',
        'has-[input:checked]:border-qb-brand has-[input:checked]:bg-(--color-qb-promo-active)',
        'has-[input:focus-visible]:outline-2 has-[input:focus-visible]:outline-offset-2 has-[input:focus-visible]:outline-qb-brand-active',
        'has-[input:disabled]:cursor-not-allowed has-[input:disabled]:opacity-50',
        className,
      )}
    >
      {icon ? (
        <span aria-hidden="true" className="flex size-10 shrink-0 items-center justify-center text-qb-brand [&_svg]:size-[26px]">
          {icon}
        </span>
      ) : null}
      <span className="min-w-0 flex-1">
        <span className="block text-qb-caption font-semibold text-qb-ink">{label}</span>
        {details ? <span className="mt-0.5 block text-qb-caption text-qb-ink-subtle">{details}</span> : null}
        {srDescription ? <span className="sr-only">{srDescription}</span> : null}
      </span>
      <input type="radio" className="peer sr-only" {...input} />
      <span
        aria-hidden="true"
        className="flex size-5 shrink-0 items-center justify-center rounded-full border-2 border-(--color-qb-check-ring) peer-checked:border-qb-brand peer-checked:bg-qb-brand [&>span]:invisible peer-checked:[&>span]:visible"
      >
        <span className="size-2 rounded-full bg-qb-surface" />
      </span>
    </label>
  );
}
