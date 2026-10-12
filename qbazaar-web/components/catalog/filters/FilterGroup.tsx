'use client';

import { useId, useState, type ReactNode } from 'react';

import { focusRing } from '@/components/design-system/focus-ring';
import { cn } from '@/lib/utils';

interface FilterGroupProps {
  title: string;
  /** Receives the heading id so a radio group or a select can be labelled by it. */
  children: (headingId: string) => ReactNode;
  /** The price group has no toggle in the design. */
  collapsible?: boolean;
  /** A labelled field of the bottom sheet (618:26974): a small label, no divider and no toggle. */
  field?: boolean;
  /** Classes of the body under the heading. */
  bodyClassName?: string;
}

/**
 * One section of the filter card (category.html aside): a 16 px / 500 heading
 * with the grey "−" (or "+" while closed) at its end, 14 px over its options,
 * between hairlines. The sheet's fields have a small label instead.
 */
export function FilterGroup({ title, children, collapsible = true, field = false, bodyClassName }: FilterGroupProps) {
  const id = useId();
  const [open, setOpen] = useState(true);
  const headingId = `${id}-heading`;
  const bodyId = `${id}-body`;
  const toggles = collapsible && !field;

  return (
    <section className={field ? 'py-2.5 first:pt-0' : 'border-t border-qb-line py-[18px] first:border-t-0 first:pt-0'}>
      <h3
        className={cn(
          'font-qb leading-normal font-medium tracking-normal',
          field ? 'text-qb-caption text-qb-ink-body' : 'text-qb-body text-qb-ink',
        )}
      >
        {toggles ? (
          <button
            type="button"
            id={headingId}
            aria-expanded={open}
            aria-controls={bodyId}
            onClick={() => setOpen((value) => !value)}
            className={cn('flex w-full cursor-pointer items-center justify-between gap-3 text-start', focusRing)}
          >
            {title}
            <span aria-hidden="true" className="text-qb-h5 font-normal text-qb-ink-subtle">
              {open ? '−' : '+'}
            </span>
          </button>
        ) : (
          <span id={headingId}>{title}</span>
        )}
      </h3>
      <div id={bodyId} hidden={!open} className={cn(field ? 'mt-3' : 'mt-3.5', bodyClassName)}>
        {children(headingId)}
      </div>
    </section>
  );
}
