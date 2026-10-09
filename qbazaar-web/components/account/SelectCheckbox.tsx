'use client';

import type { ComponentProps } from 'react';
import { Check } from 'lucide-react';

import { cn } from '@/lib/utils';

type SelectCheckboxProps = Omit<ComponentProps<'input'>, 'type'>;

/**
 * Selection box of the notifications and messages lists: 21 px, r6, a grey
 * 1.5 px ring, orange with a white tick when checked. A real checkbox, so it
 * takes a label and toggles with Space.
 */
export function SelectCheckbox({ className, ...props }: SelectCheckboxProps) {
  return (
    <span className={cn('relative inline-flex size-[21px] shrink-0', className)}>
      <input
        type="checkbox"
        className="peer size-full cursor-pointer appearance-none rounded-qb-xs border-[1.5px] border-qb-acct-check-ring bg-qb-surface transition-colors checked:border-qb-brand checked:bg-qb-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-qb-brand-active disabled:cursor-not-allowed disabled:opacity-50"
        {...props}
      />
      <Check
        aria-hidden="true"
        strokeWidth={3}
        className="pointer-events-none absolute inset-0 m-auto hidden size-3.5 text-qb-on-brand peer-checked:block"
      />
    </span>
  );
}
