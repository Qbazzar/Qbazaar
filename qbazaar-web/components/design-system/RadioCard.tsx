import type { InputHTMLAttributes, ReactNode } from 'react';

import { cn } from '@/lib/utils';

export interface RadioCardProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'type' | 'children'> {
  label: ReactNode;
  description?: ReactNode;
  /** Decorative icon in a tinted tile at the start. */
  icon?: ReactNode;
  className?: string;
}

/**
 * Selectable option card of the checkout payment methods (682:32513): a
 * native radio inside the label, so arrow keys, focus and form submission
 * work without script. The card stays white when chosen; only the radio
 * turns brand, as in the reference. Group the cards in a `role="radiogroup"`
 * labelled by the section heading, or in a `fieldset` with a `legend`.
 */
export function RadioCard({ label, description, icon, className, ...input }: RadioCardProps) {
  return (
    <label
      className={cn(
        'relative flex min-h-14 cursor-pointer items-center gap-3 rounded-qb-md border border-qb-line bg-qb-surface px-4 py-3 font-qb transition-colors',
        'hover:bg-qb-hover',
        'has-[input:focus-visible]:outline-2 has-[input:focus-visible]:outline-offset-2 has-[input:focus-visible]:outline-qb-brand-active',
        'has-[input:disabled]:cursor-not-allowed has-[input:disabled]:opacity-50',
        className,
      )}
    >
      {icon ? (
        <span
          aria-hidden="true"
          className="flex size-10 shrink-0 items-center justify-center rounded-qb-md bg-qb-brand-soft text-qb-brand [&_svg]:size-5"
        >
          {icon}
        </span>
      ) : null}
      <span className="min-w-0 flex-1">
        <span className="block text-qb-body font-medium text-qb-ink-body">{label}</span>
        {description ? <span className="mt-0.5 block text-qb-caption text-qb-ink-subtle">{description}</span> : null}
      </span>
      <input type="radio" className="peer sr-only" {...input} />
      <span
        aria-hidden="true"
        className="flex size-[19px] shrink-0 items-center justify-center rounded-full border border-qb-ink-disabled peer-checked:border-qb-brand [&>span]:invisible peer-checked:[&>span]:visible"
      >
        <span className="size-[13px] rounded-full bg-qb-brand" />
      </span>
    </label>
  );
}
